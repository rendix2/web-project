---
name: feedback-no-smartobject-magic-properties
description: "Call getX()/isX() methods directly (e.g. $presenter->getName()) instead of relying on Nette SmartObject's magic ->x property sugar"
metadata:
  node_type: memory
  type: feedback
  originSessionId: e145a0ea-8e8c-44b5-8222-d6fbbe333721
  modified: 2026-09-23T22:25:30.926Z
---

Do not use `$presenter->name` (or any similar SmartObject-magic property access to a `getX()`/`isX()` method). Call `$presenter->getName()` directly.

**Why:** The user explicitly called this out as a standing rule. Confirmed technically: `Nette\ComponentModel\Component` (which `Presenter` extends) uses the `Nette\SmartObject` trait, so `->name` really does resolve to `getName()` at runtime via `SmartObject::__get`'s reflection-based lookup — it's not a typo or broken code. But it's slower (reflection + magic method dispatch on every access) and worse for tooling (no "go to definition", no static return-type inference) than just calling `getName()` directly.

**How to apply:** Anywhere a Nette object exposes both a real `getX()`/`isX()` method and SmartObject would let it be read as `->x`, prefer the explicit method call. This applies broadly to any `SmartObject`-using class in this stack (most of Nette's own framework classes), not just `Presenter::getName()` specifically - check for a real getter/method before reaching for property-style access on framework objects.
