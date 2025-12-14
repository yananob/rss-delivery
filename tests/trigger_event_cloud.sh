#!/bin/bash
set -eu

gcloud pubsub topics publish daily-quote-event --message='{"command": ""}'
