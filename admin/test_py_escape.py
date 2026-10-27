
import json

with open("/var/www/gcm.enterprises/admin/data/documents.json", "r", encoding="utf-8") as f:
    text = f.read()

# Load with strict=False to allow raw newlines in string literals
data = json.loads(text, strict=False)
print("Parsed with strict=False! Count:", len(data))

# Re-encode to json text - json.dumps ALWAYS escapes newlines inside strings as \n
clean_json_text = json.dumps(data, indent=4, ensure_ascii=True)

with open("/var/www/gcm.enterprises/admin/data/documents.json", "w", encoding="utf-8") as f:
    f.write(clean_json_text)

print("Saved with ensure_ascii=True!")
