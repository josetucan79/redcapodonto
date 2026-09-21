#!/bin/sh
git grep TBD  | grep -v 'null, empty string, and "TBD"' | grep -v '\[null, "", "TBD"\]' | grep -v 'specify "TBD" as a placeholder.'