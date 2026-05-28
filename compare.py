import pandas as pd
import requests
import sys

EXCEL_FILE = "/home/dmitry/Downloads/Все звонки.xlsx"
TOKEN = "06929527db28d8bab086789008e318fcd1f13b2813df37bde03d0aae8dc26fbf"

def fetch_api_cdr():
    url = "http://localhost/api/mcp/v1/calls/cdr"
    headers = {"Authorization": f"Bearer {TOKEN}"}
    params = {"limit": 500}
    resp = requests.get(url, headers=headers, params=params)
    resp.raise_for_status()
    data = resp.json()
    return pd.DataFrame(data['data'])

def main():
    print("Reading Excel...")
    df_excel = pd.read_excel(EXCEL_FILE)
    print(f"Loaded {len(df_excel)} rows from Excel.")
    
    print("Fetching API data...")
    df_api = fetch_api_cdr()
    print(f"Loaded {len(df_api)} rows from API.")
    print("API columns:", df_api.columns.tolist())

    if len(df_api) > 0 and len(df_excel) > 0:
        print("\n--- Excel sample ---")
        print(df_excel.head(3))
        print("\n--- API sample ---")
        print(df_api.head(3))
        
if __name__ == "__main__":
    main()
