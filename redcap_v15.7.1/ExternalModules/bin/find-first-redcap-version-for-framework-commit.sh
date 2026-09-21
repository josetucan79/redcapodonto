#!/bin/sh

echo This script needs to be refactored now that we use git-subrepo.
exit

set -e

SCRIPTPATH="$( cd -- "$(dirname "$0")" >/dev/null 2>&1 ; pwd -P )"

commit=$1
if [ -z $commit ]; then
    echo You must specify a framework commit hash.
    exit
fi

redcapRepo=`php $SCRIPTPATH/get-redcap-version-path.php`
redcapGitDir="--git-dir $redcapRepo/.git"

git $redcapGitDir fetch

firstVersion=''
for tag in $(git $redcapGitDir tag | sort -n | tail -n 10); do
    frameworkCommitForTag=`git $redcapGitDir log $tag -1 --pretty=oneline --no-merges --grep 'Include External Module framework commit' | cut -d' ' -f7`
    if $(git merge-base --is-ancestor $commit $frameworkCommitForTag ); then
        firstVersion=$tag
        break
    fi
done

if [ -z $firstVersion ]; then
    echo 'Commit not found.  A REDCap version that contains it may not have been released yet.'
else
    echo $firstVersion
fi