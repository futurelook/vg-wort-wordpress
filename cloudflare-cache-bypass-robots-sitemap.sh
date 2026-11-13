#!/bin/bash
set -euo pipefail

# Voraussetzung: CLOUDFLARE_API_TOKEN und ZONE_ID sind lokal als Umgebungsvariablen gesetzt
# Token braucht: Zone > Cache Rules > Edit (Account-Rulesets/Filter-Lists-Rechte werden für
# diesen zonen-gebundenen Endpoint NICHT benötigt)

RULE_DESCRIPTION="Bypass cache fuer robots.txt und Sitemaps"
ENTRYPOINT_URL="https://api.cloudflare.com/client/v4/zones/$ZONE_ID/rulesets/phases/http_request_cache_settings/entrypoint"

# 1. Bestehendes Ruleset abrufen und Erfolg prüfen, bevor wir irgendetwas überschreiben
EXISTING=$(curl -s "$ENTRYPOINT_URL" \
  --header "Authorization: Bearer $CLOUDFLARE_API_TOKEN")

if ! echo "$EXISTING" | jq -e '.success' > /dev/null; then
  echo "Fehler beim Abrufen des bestehenden Rulesets:" >&2
  echo "$EXISTING" | jq '.errors' >&2
  exit 1
fi

echo "$EXISTING" | jq '.result.rules // []' > existing_rules.json

# 2. Idempotenz: nur anhängen, wenn noch keine Regel mit dieser Description existiert
ALREADY_EXISTS=$(jq --arg desc "$RULE_DESCRIPTION" \
  'any(.[]; .description == $desc)' existing_rules.json)

if [ "$ALREADY_EXISTS" = "true" ]; then
  echo "Regel '$RULE_DESCRIPTION' existiert bereits – nichts zu tun."
  exit 0
fi

# 3. Neue Regel anhängen.
#    Erfasst /robots.txt sowie alle .xml-Pfade, die "sitemap" enthalten
#    (deckt z.B. /sitemap.xml, /sitemap_index.xml UND WordPress-Core's /wp-sitemap.xml ab –
#    "starts_with(path, '/sitemap')" hätte /wp-sitemap.xml verpasst)
jq --arg desc "$RULE_DESCRIPTION" '. + [{
  "expression": "(http.request.uri.path eq \"/robots.txt\") or (http.request.uri.path.extension eq \"xml\" and http.request.uri.path contains \"sitemap\")",
  "description": $desc,
  "action": "set_cache_settings",
  "action_parameters": {
    "cache": false
  }
}]' existing_rules.json > new_rules.json

# 4. Komplette Regelliste zurückschreiben (bestehende + neue) und Erfolg prüfen
RESULT=$(curl -s "$ENTRYPOINT_URL" \
  --request PUT \
  --header "Authorization: Bearer $CLOUDFLARE_API_TOKEN" \
  --header "Content-Type: application/json" \
  --data "$(jq -n --argjson rules "$(cat new_rules.json)" '{rules: $rules}')")

if ! echo "$RESULT" | jq -e '.success' > /dev/null; then
  echo "Fehler beim Schreiben des neuen Rulesets:" >&2
  echo "$RESULT" | jq '.errors' >&2
  exit 1
fi

echo "Regel erfolgreich angelegt."

# 5. Verifizieren (robots.txt anpassen/entfernen, falls eure Sitemap woanders liegt)
curl -s -I -A "Mozilla/5.0" "https://www.android-digital.de/robots.txt" | grep -i "cf-cache-status\|cache-control"
