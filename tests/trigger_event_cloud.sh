#!/bin/bash
set -eu

gcloud pubsub topics publish rss-delivery-event --message='{"command": ""}'
