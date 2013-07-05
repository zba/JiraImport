JiraImport
---

Jira2mantis import plugin

Imports jira issues to mantis bugs, current version works only with **mysql jira backend**

tested with mantisbt-1.2.15 and jira v5.1.1#772

It imports:

 * Issues,
 * Comments
 * Issues links
 * tags
 * attachments

(I strongly suggest install only to clean mantis installs)

Usage:

 * install plugin
 * configure jira database access and map mantis and jira projects (Keep same bug numbers always ON, option currently does nothing)
 * configure priority map, resolution map, status map, relation map
  * for attaches copy `jira/data/attachments/` to directory which accessible by mantis, only DATABASE and FTP attachment scheme will work, because if mantis uses DIRECTORY attachment scheme it will use `move_uploaded_file()` function, which can't be executed successful without really file upload (however i saw some php extesion which allow this).
 
At the end script should give "Done" without any errors or additional info. 

script was done as voluntary helping for charity project http://meetcafe.org/

Jira® is trademark of Atlassian which has not affilated any way to this project. 
