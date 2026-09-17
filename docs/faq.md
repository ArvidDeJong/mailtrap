---
title: FAQ
nav_order: 7
description: Short answers about darvis/mailtrap, mail logging, email validation and Mailtrap webhooks for Laravel.
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}
