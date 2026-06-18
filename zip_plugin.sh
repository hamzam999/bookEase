#!/bin/bash

# Find repository root
REPO_ROOT=$(git rev-parse --show-toplevel 2>/dev/null)

if [ -z "$REPO_ROOT" ]; then
    echo "Error: This script must be run within a git repository."
    exit 1
fi

# Go to the root directory
cd "$REPO_ROOT" || exit 1

ZIP_NAME="booking-plugin.zip"
echo "Zipping plugin files into $ZIP_NAME (respecting .gitignore)..."

# Ensure zip command is installed
if ! command -v zip &> /dev/null; then
    echo "Error: 'zip' command not found. Please install it (e.g. sudo apt install zip)."
    exit 1
fi

# List files excluding ignored ones, filtering out the zip script and the zip itself, then zip them
git ls-files --cached --others --exclude-standard | grep -v -E "zip_plugin.sh|booking-plugin.zip" | zip "$ZIP_NAME" -@

if [ $? -eq 0 ]; then
    echo "--------------------------------------------------"
    echo "Success! Plugin zipped into: $REPO_ROOT/$ZIP_NAME"
    echo "--------------------------------------------------"
else
    echo "Failed to create zip file."
    exit 1
fi
