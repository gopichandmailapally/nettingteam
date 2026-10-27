
import json, subprocess

with open("/var/www/gcm.enterprises/admin/data/documents.json", "r") as f:
    docs = json.load(f)

print("Python json.load succeeded with", len(docs), "docs.")

# Now let us test which doc fails when passed to PHP json_decode
for i, d in enumerate(docs):
    doc_text = json.dumps(d)
    res = subprocess.run(["php", "-r", "$c = file_get_contents('php://stdin'); $x = json_decode($c, true); if ($x === null) echo json_last_error_msg();"], input=doc_text.encode("utf-8"), capture_output=True)
    err = res.stdout.decode().strip()
    if err:
        print(f"Doc {i} ({d.get('id')}) failed in PHP: {err}")
        print("Doc text preview:", doc_text[:200])
