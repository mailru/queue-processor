[![Packagist](https://img.shields.io/packagist/l/mailru/queue-processor.svg?maxAge=2592000)](https://packagist.org/packages/mailru/queue-processor)
[![Packagist](https://img.shields.io/packagist/v/mailru/queue-processor.svg?maxAge=2592000)](https://packagist.org/packages/mailru/queue-processor)
[![Build Status](https://travis-ci.org/mailru/queue-processor.svg?branch=master)](https://travis-ci.org/mailru/queue-processor)
[![codecov.io](https://img.shields.io/codecov/c/github/mailru/queue-processor.svg?maxAge=2592000)](https://codecov.io/github/mailru/queue-processor?branch=master)
[![SensioLabs Insight](https://img.shields.io/sensiolabs/i/e2a071cd-783f-434e-a4ca-8e2f46613a37.svg?maxAge=2592000)](https://insight.sensiolabs.com/projects/e2a071cd-783f-434e-a4ca-8e2f46613a37)

# Queue Processor

Queues processing tool. This is not stable version and API may change.

## Installation

The preferred way to install this tool is through [composer](http://getcomposer.org/download/).

Run:

```sh
php composer.phar require mailru/queue-processor
```

## Quick start

For demo run you can

```sh
vendor/bin/queue-processor.php --config=vendor/mailru/queue-processor/demo/config/config.php
```

Logs stored into `vendor/mailru/queue-processor/demo/shared/logs/queue-processor.log`.
