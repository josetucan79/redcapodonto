# REDCap #

REDCap is a secure web application for building and managing online surveys and databases.
The REDCap source code is available to all REDCap consortium partner institutions who have 
signed the REDCap license agreement with Vanderbilt University. [https://projectredcap.org](https://projectredcap.org/)

The [latest version](https://redcap.vumc.org/community/custom/download.php) may be downloaded from the [REDCap Community site](https://redcap.vumc.org/community/).

**To deploy a fresh install of REDCap on your server from scratch** (i.e., without using the install zips hosted on REDCap Community), follow these steps:
 1. Download the install zip file (named `redcapXX.X.X.zip`) attached to the [GitHub Release](https://github.com/vanderbilt-redcap/REDCap/releases) for the desired REDCap version. Extract the files from the `redcap` folder from the zip into the webroot of a web server.
 2. Navigate to the REDCap Install page in a web browser - e.g., https://yourserver.edu/redcap/install.php - and follow the instructions to complete the installation process.

**Deploying new releases to REDCap Community:** The REDCap Development Team can release new versions to the consortium 
simply by creating a new GitHub Release, which triggers a GitHub Actions workflow. Once the release has been created, 
it will automatically send an email when done with the required steps to finalize the release in order to make it 
publicly available and downloadable on REDCap Community.

**Helpful notes for developers:**
 - **Database queries:**
   - **db_query()** - For querying the database, you should use `db_query()` and other `db_*` global functions. These functions are merely aliases for PHP's `mysqli_*` procedural functions. It is recommended that you use parameterized queries, which can be constructed by passing an array of query parameters as the function's second parameter - e.g., `$q = db_query("select * from redcap_metadata where project_id = ? and field_name = ?", [$pid, $field]);`.
   - **"redcap_data" table** - When querying the redcap_dataX table for a project, you should use `REDCap::getData()` and `REDCap::saveData()`. But if you need to write a direct query to the data table, you should never use "redcap_data" literally in the query because each project's data may be in any one of the data tables, so you should instead use `REDCap::getDataTable()` to retrieve the data table name for a project to use in the query - e.g., `$q = db_query("select * from ".REDCap::getDataTable($pid)." where project_id = ?", [$pid]);`.
 - **Language text:** All stock/UI text should never be hardcoded in PHP and JavaScript files but should instead be added as a uniquely-named language variable in `LanguageUpdater/English.ini`.
   - **RCView::tt()** - To reference language variables inside PHP files, you should use `RCView::tt($lang_var)` for outputting text onto a webpage - e.g., `print RCView::div(['class'=>'m-2'], RCView::tt('global_01') );`. If the language string contains placeholder values, seen as a number inside curly brackets, then you should use `RCView::tt_i()` with an array of the values to be inserted. For example, assuming the language variable "config_03" to be "As a user in DAG {0} and Role {1}...", you might have `print RCView::tt_i('config_03', [$group_name, $role_name]);`.
   - **addLangToJS()** - To reference language variables inside JavaScript files, you should first output the language strings via the PHP function `addLangToJS($lang_vars_array)`. For example, `addLangToJS(['report_190', 'report_191']);` will output the language strings as the JavaScript object attributes `lang.report_190` and `lang.report_190` on the webpage. Then your JavaScript code can reference the language strings as such: `simpleDialog(lang.report_190, lang.report_191);`.
   - NOTE: It is no longer recommended that developers output text using the global array `$lang` directly - e.g., `print "<div>".$lang['global_01']."</div>";`. This is an older convention, and the newer convention using `RCView::tt()` and other  `RCView::tt_*()` methods should be utilized instead.
- **Frontend libraries:** REDCap comes bundled with several third-party frontend libraries that developers may utilize in their code. Below is a list of those libraries that are approved for use in REDCap code and in External Modules.
  1. [Bootstrap 5](https://getbootstrap.com/docs/5.0/getting-started/introduction/)
  1. [DataTables](https://datatables.net/)
  1. [Font Awesome](https://fontawesome.com/icons)
  1. [Sweet Alert](https://sweetalert2.github.io/)
  1. [Select2](https://select2.org/)
  1. [VueJS](https://vuejs.org/)
  1. [Twig (in External Modules)](https://twig.symfony.com/)

**Developer notes regarding changes for Accessibility:**  
It is important that when we make changes for accessibility purposes, that we stay consistent. For example, if color `#XXX` is replaced with color `#YYY`, then as much as possible throughout the codebase, for the same background color, `#XXX` should always be replaced with `#YYY`.   
Below is a tabulation of the colors that are being replaced so far in REDCap in order to improve accessibility on several different pages.  
If while making changes for accessibility purposes you encounter a color that matches one of the colors in the "Before" column, then use the color in the "After" column for replacement.

| Bg Color Before  | Bg Color After | Color Before     | Color After | Comments                                                                 |
|------------------|----------------|------------------|-------------|--------------------------------------------------------------------------|
| #E7E7E7, #E2E2E2 |                | #008000 (green)  | #007500     |                                                                          |
| #ECECEC, #EEE    |                | #777, #888       | #6B6B6B     |                                                                          |
| #FFF             |                | #888, #777       | #707070     |                                                                          |
| #FFF             |                | #999             | #757575     |                                                                          |
| #F7F7F7          |                | #777             | #707070     |                                                                          |
| #E7E7E7          |                | #3E72A8          | #3A699C     |                                                                          |
| #3498db          | #007dbb        | #3498db          | #007dbb     |                                                                          |
| #3498db          | #007dbb        | #CCC             | #FFF        | The color #3498db throughout the codebase has been replaced with #007dbb |
| #aaa             | #707070        | #FFF             |             |                                                                          |
| #FAFAFA          |                | #aaa, #777       | #737373     |                                                                          |
| #FAFAFA, #F3F3F3 |                | red (#FF0000)    | #E00000     |                                                                          |
| #FAFAFA          |                | #BC8900          | #946C00     |                                                                          |
| #fcfef5          | #FEFFFA        |                  |             |                                                                          |
| #F0F0F0          |                | #777, #888, #999 | #6E6E68     |                                                                          |


