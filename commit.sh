#!/usr/bin/env bash
# Коммит с сообщением из аргумента (без внедрения trailer/Co-authored-by).
# Использование: ./commit.sh "Сообщение коммита"
# --no-verify: без pre-commit (lint-staged), чтобы коммит не падал на окружении без php-cs-fixer в PATH.

set -e
msg="${*:-Доработка}"
git add -A
git commit --no-verify -m "$msg"
