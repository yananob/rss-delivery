#!/bin/bash
set -eu

curl localhost:8081 \
    -H "ce-id: 9999999999" \
    -H "ce-source: //pubsub.googleapis.com/projects/test-pj/topics/rss-delivery-event" \
    -H "ce-specversion: 1.0" \
    -H "ce-type: com.google.cloud.pubsub.topic.publish" \
    -d '{
        "message": {
          "data": ""
        },
        "subscription": "projects/test-pj/subscriptions/rss-delivery-event"
      }'
