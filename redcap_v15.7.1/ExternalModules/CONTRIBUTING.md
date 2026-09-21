## Contributing Additions/Changes to the External Module Framework
If you have any colleagues that need access to this repo, please email their GitHub username to `redcap-external-module-framework@vumc.org`.

[Pull requests](https://docs.github.com/en/github/collaborating-with-issues-and-pull-requests/about-pull-requests) are always welcome.  Unless explicitly stated upfront, all changes in approved pull requests will be supported by Vanderbilt (not the code's original author).

### How to set up a EM Framework development environment

To override the version of the EM Framework bundled with REDCap for development, clone this repo into a directory named **external_modules** under your REDCap web root (e.g., /www/external_modules/).  

### Adding to or modifying the EM Framework

- Partial pull requests (with placeholders, pseudocode, etc.) are also welcome if you have an idea but are not sure how to fully implement it.
- To add a new method for calling from modules, add it to the [Framework](https://github.com/vanderbilt/redcap-external-modules/blob/testing/classes/framework/Framework.php) class.  Depending on the context, it may make sense to add new methods to one of the helper classes ([Project](https://github.com/vanderbilt/redcap-external-modules/blob/testing/classes/framework/Project.php), [Form](https://github.com/vanderbilt/redcap-external-modules/blob/testing/classes/framework/Form.php), [User](https://github.com/vanderbilt/redcap-external-modules/blob/testing/classes/framework/User.php), etc.) returned by their respective getter methods (e.g. `$module->getProject()`).  Please also include [documentation](docs/methods/README.md) for your new method in your pull request.
- To reference REDCap versions in code or documentation, simply specify "TBD" as a placeholder.  This will be detected and the appropriate version will be inserted when the changes make it into a REDCap release.
- Please consider potential long term support consequences and next growth steps of any given change.  [Framework Versioning](docs/versions/README.md) typically allows graceful transitions during most breaking changes, but can still require complex coordination over time (REDCap updates, module updates, and manual project configuration or data updates on every project at every institution).
- Adding unit tests should be considered when refactoring existing code (especially when behavioral change is not intended).
- Unit testing is recommended but not required for new features.

### Here is Mark's personal strategy for contributing back to the framework:
- Prototype any new or modified framework methods inside whatever module for which you need the changes.
- Try to write them so that they would work if copy pasted into the framework
- Once they're mature & well tested, create a pull request.
- Simply leave them duplicated in your module for now.  I typically just add a comment saying: _"A pull request has been created to merge this method into the module framework.  This method can be removed once the PR is merged and this module's minimum REDCap version is updated accordingly."_

### Running Unit Tests

GitHub will automatically run unit tests on any PRs created.  Running them locally on a *nix environment is possible by running `./run-tests.sh` from the command line, or `vendor/bin/phpunit --exclude slow` to exclude a small number of very slow tests.  As of 7/3/24 on an i7-12800H, all tests take about 94 seconds, while only the fast tests take 13 seconds. Running tests natively (or in WSL) is recommended.  If your MySQL instance is in docker, you can forward the port to make it available from your native command line.  Running tests directly from a docker container is not recommended as cross filesystem volume mounts may introduce a multiple order of magnitude performance hit.
