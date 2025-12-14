#!/bin/bash
set -eu

curl localhost:8081 \
    -H "ce-id: 9999999999" \
    -H "ce-source: //pubsub.googleapis.com/projects/test-pj/topics/daily-quote-event" \
    -H "ce-specversion: 1.0" \
    -H "ce-type: com.google.cloud.pubsub.topic.publish" \
    -d '{
        "message": {
          "data": "eyJ0b3BpYyI6ICJvbWwtdXBkYXRlIiwgImNvbW1hbmQiOiAiYmF0Y2gtdXBkYXRlLWJvb2tzIn0="
        },
        "subscription": "projects/test-pj/subscriptions/daily-quote-event"
      }'
# data: php -r 'echo base64_encode("{\"topic\": \"oml-event\", \"command\": \"batch-update-books\"}");'
