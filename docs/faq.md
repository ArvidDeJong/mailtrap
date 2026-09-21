---
title: "FAQ"
nav_order: 10
description: "Short answers about darvis/mailtrap: what it is, whether it is official, versions, cost, how validation and blocking work, webhook security and the inbox page."
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}
