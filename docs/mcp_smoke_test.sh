#!/usr/bin/env bash
#
# Smoke test for the MCP Analytics API.
#
# Runs every endpoint against a live deployment and checks for HTTP 200 +
# a {query,data,meta} envelope. Used right after deploying to prod (A.13)
# and as a periodic sanity check.
#
# Usage:
#   MCP_API_BASE=https://tiktak.by/api/mcp/v1 \
#   MCP_API_TOKEN=... \
#   ./docs/mcp_smoke_test.sh
#
# Exit code is the number of failed endpoints.

set -u

BASE="${MCP_API_BASE:-http://localhost/api/mcp/v1}"
TOKEN="${MCP_API_TOKEN:?MCP_API_TOKEN env var is required}"

PASS=0
FAIL=0
FAILED_URLS=()

color() { printf "\033[%sm%s\033[0m" "$1" "$2"; }
ok()   { color "32" "PASS"; }
nope() { color "31" "FAIL"; }

# hit <relative-path> [<python-expression>]
#   python-expression: optional Python expression evaluated against the
#                      parsed JSON (variable name `r`). Must return truthy.
#                      Use Python syntax — not jq.
hit() {
    local path="$1"
    local check="${2:-True}"
    local url="${BASE}${path}"

    local body status
    body=$(curl -fsS -m 30 -H "Authorization: Bearer ${TOKEN}" "$url" 2>/dev/null)
    status=$?

    if [[ $status -ne 0 ]]; then
        printf "  %s  %s  (curl failed)\n" "$(nope)" "$path"
        FAIL=$((FAIL+1))
        FAILED_URLS+=("$path")
        return
    fi

    # CSV endpoints aren't JSON envelopes; just check non-empty and look for a header.
    if [[ "$path" == /export/monthly/* ]]; then
        if [[ -z "$body" ]]; then
            printf "  %s  %s  (empty body)\n" "$(nope)" "$path"
            FAIL=$((FAIL+1)); FAILED_URLS+=("$path"); return
        fi
        printf "  %s  %s  (CSV %d bytes)\n" "$(ok)" "$path" "${#body}"
        PASS=$((PASS+1)); return
    fi

    # OpenAPI spec is JSON but not envelope.
    if [[ "$path" == "/openapi.json" ]]; then
        local result
        result=$(echo "$body" | python3 -c '
import json, sys
try:
    s = json.load(sys.stdin)
    print(len(s.get("paths", {})) if "openapi" in s and "paths" in s else "")
except Exception:
    print("")
')
        if [[ -n "$result" ]]; then
            printf "  %s  %s  (%s paths)\n" "$(ok)" "$path" "$result"
            PASS=$((PASS+1))
        else
            printf "  %s  %s  (not a valid OpenAPI doc)\n" "$(nope)" "$path"
            FAIL=$((FAIL+1)); FAILED_URLS+=("$path")
        fi
        return
    fi

    # Standard envelope + extra-check via python.
    local pycheck
    pycheck=$(echo "$body" | CHECK="$check" python3 -c '
import json, os, sys
try:
    r = json.load(sys.stdin)
except Exception as e:
    print("invalid_json:" + str(e)); sys.exit(1)

# Envelope sanity
if "query" not in r or "data" not in r or "meta" not in r:
    print("envelope_missing"); sys.exit(1)
if r["meta"].get("currency") != "BYN":
    print("envelope_missing"); sys.exit(1)

# Custom extra check
expr = os.environ.get("CHECK", "True")
try:
    ok = bool(eval(expr, {"__builtins__": {}}, {"r": r, "max": max, "min": min, "len": len, "round": round, "any": any, "all": all}))
except Exception as e:
    print("check_error:" + str(e)); sys.exit(1)
if not ok:
    print("check_failed"); sys.exit(1)
print("ok")
')
    if [[ "$pycheck" == "ok" ]]; then
        printf "  %s  %s\n" "$(ok)" "$path"
        PASS=$((PASS+1))
    else
        printf "  %s  %s  (%s)\n" "$(nope)" "$path" "$pycheck"
        FAIL=$((FAIL+1)); FAILED_URLS+=("$path")
    fi
}

echo "Smoke testing ${BASE}"
echo

echo "── Health & spec ──"
hit "/health" 'r["data"]["status"] == "ok"'
hit "/openapi.json"

echo
echo "── Meta ──"
hit "/meta/categories" 'len(r["data"]["business_categories"]) == 6'
hit "/meta/locations"  'len(r["data"]) >= 4'
hit "/meta/expense-items" 'len(r["data"]) > 0'
hit "/meta/income-items"  'len(r["data"]) > 0'
hit "/meta/data-freshness" "'rent_deals_arch' in r['data']['tables']"

echo
echo "── Finance (acceptance: legacy-parity numbers, 2025 has fy2025 warning) ──"
# 2019 baseline (matches /bb/dohrash2.php exactly): revenue 424231.72 / ebitda 25484.82
hit "/finance/pnl?from=2019-01-01&to=2019-12-31&granularity=year" 'round(r["data"][0]["revenue_byn"]) == 424232 and round(r["data"][0]["ebitda_byn"]) == 25485'
# carnival/non-carnival split must sum to total
hit "/finance/pnl?from=2019-01-01&to=2019-12-31&granularity=year" 'abs(r["data"][0]["revenue_non_carnival_byn"] + r["data"][0]["revenue_carnival_byn"] - r["data"][0]["revenue_byn"]) < 0.5'
# 2024 baseline: revenue 293189.25 / ebitda -12950.45
hit "/finance/pnl?from=2024-01-01&to=2024-12-31&granularity=year" 'round(r["data"][0]["revenue_byn"]) == 293189 and round(r["data"][0]["ebitda_byn"]) == -12950'
hit "/finance/pnl?from=2025-01-01&to=2025-12-31&granularity=year" "any(w['code'] == 'fy2025_bank_channel_gap' for w in r['meta']['warnings'])"
hit "/finance/revenue?from=2024-01-01&to=2024-12-31&granularity=quarter"
# include_carnival=false zeroes the carnival column
hit "/finance/pnl?from=2019-01-01&to=2019-12-31&granularity=year&include_carnival=false" 'r["data"][0]["revenue_carnival_byn"] == 0'
hit "/finance/expenses?from=2024-01-01&to=2024-12-31&channel=bank"
hit "/finance/cash-flow?from=2024-01-01&to=2024-12-31&granularity=quarter"

echo
echo "── Operations (acceptance: office 3 visible in 2019 via sub_deal.place, residual after closure) ──"
hit "/operations/funnel?from=2024-01-01&to=2024-12-31"
hit "/operations/timeline?from=2024-01-01&to=2024-12-31&granularity=quarter"
hit "/operations/by-category?from=2024-01-01&to=2024-12-31"
# Office 3 (Pobediteley) was the top revenue office in 2019
hit "/operations/by-location?from=2019-01-01&to=2019-12-31" '3 in [d["office_id"] for d in r["data"]]'
# After closure: only residual cl_payment subdeals — should be tiny (< 10k BYN)
hit "/operations/by-location?from=2022-08-01&to=2026-04-30" 'sum(d["revenue_byn"] for d in r["data"] if d["office_id"] == 3) < 10000'

echo
echo "── Inventory ──"
hit "/inventory/free-tree"
hit "/inventory/profitability?min_deals=5"
hit "/inventory/utilization?from=2024-01-01&to=2024-12-31&category=children"
hit "/inventory/turnover?from=2024-01-01&to=2024-12-31"
hit "/inventory/idle?days=180"

echo
echo "── Customers ──"
hit "/customers/timeline?from=2024-01-01&to=2024-12-31&granularity=quarter"
hit "/customers/cohorts?from=2024-01-01&to=2024-06-30"
hit "/customers/repeat-intervals?from=2024-01-01&to=2024-12-31" "'median_days' in r['data']"
hit "/clients/ltv?limit=5"

echo
echo "── Geo / Locations / Categories / Carnival ──"
hit "/geo/clients-by-city?from=2024-01-01&to=2024-12-31"
hit "/locations/performance?from=2024-01-01&to=2024-12-31&granularity=quarter"
hit "/locations/lifecycle"
hit "/categories/performance?date_from=2024-01-01&date_to=2024-12-31"
hit "/categories/seasonality?category=costumes&years=5" 'max(d["seasonality_index"] for d in r["data"]) == [d["seasonality_index"] for d in r["data"] if d["month_num"]==12][0]'
hit "/carnival/funnel?from=2024-01-01&to=2024-12-31"
hit "/carnival/seasonality?years=5" 'max(d["seasonality_index"] for d in r["data"]) == [d["seasonality_index"] for d in r["data"] if d["month_num"]==12][0]'
hit "/carnival/revenue?from=2024-01-01&to=2024-12-31&granularity=quarter"

echo
echo "── Export ──"
hit "/export/monthly/pnl?from=2019-01-01&to=2019-12-31"
hit "/export/monthly/revenue?from=2024-01-01&to=2024-12-31"
hit "/export/monthly/operations?from=2024-01-01&to=2024-12-31"
hit "/export/monthly/traffic"

echo
printf "%s   passed: %d   failed: %d\n" "──────────" "$PASS" "$FAIL"
if (( FAIL > 0 )); then
    printf "Failed endpoints:\n"
    for u in "${FAILED_URLS[@]}"; do printf "  - %s\n" "$u"; done
fi
exit "$FAIL"
