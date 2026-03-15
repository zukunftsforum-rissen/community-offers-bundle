#!/bin/bash

current=$(git rev-parse --abbrev-ref HEAD)

if [ "$current" != "main" ]; then
  echo "Already on branch $current"
  exit 0
fi

read -p "Branch name: " branch

git switch -c "$branch"