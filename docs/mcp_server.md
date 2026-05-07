# Инструкция по установке и настройке MCP-сервера аналитики

Архитектура состоит из двух слоев: защищенного API на базе Laravel (`tiktakby`) и легковесного прокси-сервера на Node.js (`mcp-tiktak`) для Claude Desktop.

## 1. Изменения в основной базе (Laravel-проект `tiktakby`)

Для поддержки MCP-сервера в проект были внедрены следующие изменения:

* **`routes/api.php`** — добавлена группа маршрутов `/mcp/v1/` с ограничением в 60 запросов в минуту (rate-limit).
* **`app/Http/Controllers/McpAnalyticsController.php`** — контроллер для сбора и отдачи аналитических данных (инвентарь, LTV, эффективность категорий). Личные данные клиентов не передаются.
* **`app/Http/Middleware/McpTokenMiddleware.php`** — фильтр для авторизации запросов через Bearer-токен.
* **`app/Http/Middleware/McpGeoCountryMiddleware.php`** — фильтр гео-блокировки по локальной базе `GeoLite2` (разрешены только BY и RU).
* **`app/Http/Middleware/McpAuditLogMiddleware.php`** — аудит-лог каждого MCP-запроса в базу (таблица `mcp_api_log`) с удалением персональных данных из `query_params`.
* **`config/mcp.php`** и **`.env`** — конфигурация. Переменные среды: `MCP_API_TOKEN`, `MCP_GEO_ALLOWED_COUNTRIES`, `MCP_CACHE_TTL`.

## 2. Установка Node.js MCP-сервера

Сам сервер-адаптер находится по пути: `/home/dmitry/sites/mcp-tiktak`

### Требования
- Node.js 18+ (используется Node.js 20 через менеджер `fnm` по пути `/home/dmitry/.fnm`)
- Доступ к токену из файла `.env` основной базы `tiktakby`

### Настройка сервера
1. Скопируйте `.env.example` в `.env` внутри `/home/dmitry/sites/mcp-tiktak`:
   ```bash
   cd /home/dmitry/sites/mcp-tiktak
   cp .env.example .env
   ```
2. Откройте `.env` и задайте токен, сгенерированный и прописанный в `.env` файле Laravel (`MCP_API_TOKEN`):
   ```
   TIKTAK_API_BASE=http://localhost/api/mcp/v1
   TIKTAK_API_TOKEN=ВАШ_СГЕНЕРИРОВАННЫЙ_ТОКЕН
   ```
3. Установите зависимости (если еще не установлены):
   ```bash
   npm install
   ```

## 3. Настройка интеграции с Claude Desktop

Чтобы Claude Desktop имел возможность вызывать инструменты аналитики (tools), его необходимо сконфигурировать на запуск Node.js-сервера.

Файл конфигурации Claude Desktop находится в следующих директориях в зависимости от ОС:
* **Windows**: `%APPDATA%\Claude\claude_desktop_config.json`
* **macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`
* **Linux (неофициальный клиент)**: `~/.config/Claude/claude_desktop_config.json`

### Пример конфигурации:

```json
{
  "mcpServers": {
    "tiktak_analytics": {
      "command": "/home/dmitry/.fnm/node-versions/v20.20.2/installation/bin/node",
      "args": ["/home/dmitry/sites/mcp-tiktak/src/index.js"],
      "env": {
        "TIKTAK_API_BASE": "http://localhost/api/mcp/v1",
        "TIKTAK_API_TOKEN": "ВАШ_СГЕНЕРИРОВАННЫЙ_ТОКЕН"
      }
    }
  }
}
```

> **Важно**: Десктопные приложения обычно не подтягивают переменные `PATH` из оболочки (bash/zsh). Поэтому в настройках `command` необходимо указывать жесткий (абсолютный) путь к бинарному файлу `node`, установленному через `fnm`. 

## 4. Генерация токена доступа
На данный момент пользовательский интерфейс для генерации токена в панели управления отсутствует. Токен генерируется вручную из командной строки сервера (например, командой `openssl rand -hex 32`) и прописывается в `.env` файлах как Laravel-бэкенда, так и Node.js MCP-сервера/Claude Desktop.
