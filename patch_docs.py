import json
import re

# Update mcp_server.md
with open('docs/mcp_server.md', 'r') as f:
    content = f.read()

new_row = "|        | `POST /pages/listing/{slug}/image` | Upload and resize hero-image for L2 category (saves as JPG, updates h1_pic_url) |\n"
content = re.sub(r'(\|\s+\|\s+`PATCH /pages/listing/\{slug\}`.+?\n)', r'\1' + new_row, content)

with open('docs/mcp_server.md', 'w') as f:
    f.write(content)

# Update mcp-v1.json
with open('resources/openapi/mcp-v1.json', 'r') as f:
    spec = json.load(f)

slug_path = spec['paths']['/pages/listing/{slug}']
spec['paths']['/pages/listing/{slug}/image'] = {
    "post": {
        "summary": "Upload hero image for listing page",
        "description": "Uploads an image, resizes it to 1440x635, preserves aspect ratio with #F3F9FF background, saves as JPEG, and updates the h1_pic_url field in the pages table.",
        "operationId": "uploadListingImage",
        "tags": ["Pages"],
        "parameters": [
            {
                "name": "slug",
                "in": "path",
                "required": True,
                "description": "Slug of the listing page",
                "schema": {
                    "type": "string"
                }
            }
        ],
        "requestBody": {
            "required": True,
            "content": {
                "multipart/form-data": {
                    "schema": {
                        "type": "object",
                        "properties": {
                            "image": {
                                "type": "string",
                                "format": "binary",
                                "description": "Image file to upload (jpeg, jpg, png, webp, max 5MB)"
                            }
                        },
                        "required": ["image"]
                    }
                }
            }
        },
        "responses": slug_path['patch']['responses']
    }
}

# The FAQ property is also missing in PATCH if it wasn't added yet!
# The user's earlier PR added faq, let's see if we need to add FAQ property to the schema
try:
    schema_props = slug_path['patch']['requestBody']['content']['application/json']['schema']['properties']
    if 'faq' not in schema_props:
        schema_props['faq'] = {
            "type": "array",
            "items": {
                "type": "object",
                "properties": {
                    "question": {"type": "string"},
                    "answer": {"type": "string"}
                }
            }
        }
except KeyError:
    pass

with open('resources/openapi/mcp-v1.json', 'w') as f:
    json.dump(spec, f, indent=4)

