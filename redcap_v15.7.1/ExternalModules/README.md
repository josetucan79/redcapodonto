**PULL REQUESTS:** Please create pull requests against the **testing** branch.

# REDCap External Modules

This repository represents the development work for the REDCap External Modules framework, which is a class-based framework for plugins and hooks in REDCap. External Modules is an independent and separate piece of software from REDCap, and is included natively in REDCap 8.0.0 and later.

**[Click here](docs/methods/README.md) for method documentation.**

## Usage

You can install modules using the "Repo" under "External Modules" in the REDCap Control Center.  All modules are open source, and the Repo provides links to the GitHub page for each.  If you want to create your own module, see the [Official External Modules Documentation](docs/README.md).

## Contributing Additions/Changes
See [CONTRIBUTING.md](CONTRIBUTING.md)

## Branch Descriptions
- **testing** - This is the framework version currently being tested on the Vanderbilt's REDCap Test server.  Changes (including pull requests) are committed/merged here first.
- **production** - This is the framework version deployed to Vanderbilt's REDCap Production servers.  Changes are merged here once they've been tested and determined stable and supportable.  This typically happens once changes have been in the **testing** branch for a week without any issues.  Vanderbilt DataCore team members can see more information about this process in our private [Weekly Merge Procedure](https://github.com/vanderbilt-redcap/datacore/wiki/External-Module-Framework-Weekly-Merge-Procedure) document.
- **release** - This is the framework version bundled with REDCap for release to the consortium.  Changes are typically merged here once they've been on Vanderbilt's REDCap Production servers for at least a week.
