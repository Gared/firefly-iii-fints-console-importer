# Firefly III FinTS Console Importer

Import your bank transactions into [Firefly III](https://www.firefly-iii.org/) using the [FinTS](https://www.hbci-zka.de/) protocol (used by most German banks).

What does this tool do?
* Connects to your bank via the FinTS protocol
* Fetches your bank transactions for a given time period
* Maps and imports the transactions into your Firefly III instance
* Persists the FinTS session state to avoid unnecessary re-authentication
* Supports multiple bank accounts via separate configuration files

## Requirements

You need PHP 8.5 or higher to run this tool.

## Setup

### 1. Generate a configuration file

Before you can run the importer, you need to generate a configuration file for your bank account.
This will also create a state file to persist the FinTS session state (which is required to run the importer).
Run the interactive setup wizard:

```bash
bin/console config:generate
```

You will be prompted for the following information:
- FinTS API URL of your bank (e.g. `https://fints.ing.de/fints/`)
- Bank code (BLZ)
- Your online banking username and password
- Your Firefly III instance URL and personal access token
- TAN mode and TAN medium (if required by your bank)
- The Firefly III asset account to import into

The configuration file will be saved to the `data/config/` directory.

### 2. Validate the configuration file (optional)

You can verify that a configuration file is valid before running the importer:

```bash
bin/console config:validate data/config/mybank.json
```

### 3. Run the importer

```bash
bin/console import-transactions --config data/config/mybank.json
```

## Usage

### Docker

Run the application using the official Docker image:

```bash
docker run --rm -v data_directory:/app/data/ gared/firefly-iii-fints-console-importer bin/console import-transactions --config data/config/mybank.json
```

Or build the image manually:

```bash
docker build -f docker/Dockerfile -t firefly-fints-importer .
docker run --rm -v $(pwd)/data:/app/data firefly-fints-importer bin/console import-transactions --config data/config/mybank.json
```

### Clone

Clone this repository and install dependencies:

```bash
git clone https://github.com/gared/firefly-iii-fints-console-importer.git
cd firefly-iii-fints-console-importer
composer install
```

Then run the importer:

```bash
bin/console import-transactions --config data/config/mybank.json
```

## Configuration file

The configuration file is a JSON file located in `data/config/`. It is generated interactively via `config:generate` and contains the following fields:

| Field                                          | Description                                                 |
|------------------------------------------------|-------------------------------------------------------------|
| `bank_username`                                | Your online banking username                                |
| `bank_password`                                | Your online banking password                                |
| `bank_code`                                    | Bank code (BLZ)                                             |
| `bank_url`                                     | FinTS API URL of your bank                                  |
| `bank_2fa`                                     | TAN mode (e.g. `NoPsd2TanMode` or a specific TAN method ID) |
| `bank_2fa_device`                              | TAN medium / device name (if required)                      |
| `firefly_url`                                  | URL of your Firefly III instance                            |
| `firefly_access_token`                         | Personal access token for Firefly III                       |
| `choose_account_automation.bank_account_iban`  | IBAN of the bank account to import from                     |
| `choose_account_automation.firefly_account_id` | ID of the Firefly III asset account to import into          |
| `choose_account_automation.from`               | Start date for the import (e.g. `now - 2 days`)             |
| `choose_account_automation.to`                 | End date for the import (e.g. `now`)                        |

The config is fully compatible with https://github.com/bnw/firefly-iii-fints-importer, but doesn't use all config values.

## Available commands

| Command                               | Description                                        |
|---------------------------------------|----------------------------------------------------|
| `config:generate`                     | Interactively generate a new configuration file    |
| `config:validate <file>`              | Validate an existing configuration file            |
| `import-transactions --config <file>` | Run the importer with the given configuration file |

## Development

Install all dependencies including dev dependencies:

```bash
composer install
```

Run the test suite:

```bash
vendor/bin/phpunit
```

Run static analysis:

```bash
vendor/bin/phpstan
```

Run code style fixer:

```bash
vendor/bin/php-cs-fixer fix
```


