# Security Policy

## Supported versions

Please report against the latest release, and confirm you can reproduce the issue on an up-to-date install first. Older versions are looked at case by case.

## Reporting a vulnerability

**Please do not report security vulnerabilities through public GitHub issues, pull requests, or discussions.**

Instead, report them privately using GitHub's [private vulnerability reporting](https://github.com/estin92/laravel-dvla-ves/security/advisories/new) for this repository. If that is unavailable to you, contact the maintainer privately via their GitHub profile at https://github.com/estin92.

Please include enough detail to reproduce the issue:

- the package version,
- the affected code path or method,
- steps to reproduce, and
- the impact you observed.

You can expect an acknowledgement of your report, and we will keep you informed as the issue is investigated and resolved. Please give a reasonable amount of time for a fix to be released before any public disclosure.

## A note on credentials

This package authenticates to the DVLA Vehicle Enquiry Service with an API key read from configuration (`DVLA_VES_API_KEY`). Treat that key as a secret: keep it in your environment, never commit it, and rotate it if it is exposed. The package does not log the key, but enabling `DVLA_VES_DEBUG_LOG_RESPONSES` writes raw API responses to disk — review where that output is stored before turning it on in production.
