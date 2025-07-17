# Mroonga Search for Omeka S (Modified Version)

This is a modified version of the original Mroonga Search module by Kentaro Fukuchi.

## Original Module

**Original Author:** Kentaro Fukuchi  
**Original Repository:** https://github.com/fukuchi/Omeka-S-module-mroonga-search  
**Original License:** MIT License

## About This Modified Version

This modified version maintains the core functionality of the original module while incorporating improvements and fixes for modern Omeka S installations.

## Description

Mroonga search is a module for [Omeka S](https://omeka.org/s/) that enables
CJK-ready full-text search by activating the [Mroonga](https://mroonga.org/)
plugin of MySQL or MariaDB.

The default installation of the full-text search feature of the Omeka S is not
CJK (Chinese, Japanese, Korean) ready because of the limitation of the database
engine (MySQL or MariaDB). The Mroonga plugin extends the database to achieve
CJK-ready search. This module simply activates this plugin by modifying the
table information that used by Omeka S.

## Installation

### Preparation

First of all, **back up your database**. This module modifies the table schema,
and that may cause unrecoverable failure.

Before installing this module, install and configure the Mroonga plugin to
enable the Mroonga storage engine. For example, if you use MariaDB on Debian or
Ubuntu machine, install 'mariadb-plugin-mroonga' package. Please read the
[official document](https://mroonga.org/docs/install.html) for further
information.

### From ZIP

Download the latest release from this repository and unzip it in the
`modules` directory of Omeka S, then enable the module from the admin
dashboard. Read the
[user manual of Omeka S](https://omeka.org/s/docs/user-manual/modules/)
for further information.

### From GitHub

```bash
git clone https://github.com/fukudakz/Omeka-S-module-mroonga-search.git
cd Omeka-S-module-mroonga-search
# Rename the directory to MroongaSearch in the modules directory
```

### Configuration

No configuration is needed. Once installed, the database will be updated,
enabling full-text search.

### Uninstall

Simply uninstall this module to remove Mroonga settings from your database.
No additional work is needed.

## Notes

This module highly depends on the database structure of Omeka S 3.x and 4.x. If you are
upgrading Omeka S from 3.x to 4.x or later, we highly recommend you uninstall
this module **before upgrading**.

We have not heavily tested the Mroonga engine with large-sized data yet. For
an advanced full-text search, we recommend that you check the
[Solr module](https://omeka.org/s/modules/Solr/).

Currently, this module uses the default N-gram parser. MeCab or any parsers are
not supported yet.

### Technical note

MroongaSearch module changes the storage engine of the fulltext\_search table
of your Omeka S instance from InnoDB to Mroonga. This enables CJK-friendly fast
full-text search while it increases the size of the database.

## Changes from Original

- Updated compatibility with Omeka S 4.x
- Improved error handling and validation
- Enhanced documentation and installation instructions
- Bug fixes and code improvements

## TODOs

* Enabling synonyms
* Support for additional parsers (MeCab, etc.)
* Performance optimizations

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This modified version is released under the MIT License, same as the original.

Copyright (c) 2020, 2021 Kentaro Fukuchi (Original)  
Copyright (c) 2025 [Your Name] (Modified version)

See the `LICENSE` file for details.
