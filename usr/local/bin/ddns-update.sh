#!/bin/bash

# Base of dynamic domains, which is combined with the ddns NAME
# parameter, e.g. if we want ddns entry "home" to point to
# home.d.my-site.net then set DOMAIN=d.my-site.net and
# ddns-update "update home <ip-address>".
DOMAIN=d.my-domain.co.uk

# "status-all" requires that DOMAIN is of the form "d.my-domain.co.uk"
# Modify the QUERY for status-all if your dynamic dns names are not of this
# form.

# Create a separate database user and grant appropriate permissions to the
# powerdns database, rather than using the "powerdns" user.
POWERDNS_USER=powerdns
POWERDNS_PASSWORD=powerdns-password

# Log file
LOG="/var/log/ddns-update.log"

# Get parameters
ACTION=${1,,}
NAME=${2,,}
ADDR=$3

echo "" >> $LOG;
echo "Command: $*" >> $LOG;

# Validate/sanitise input

# Validate action
if ! [[ $ACTION =~ ^(create|update|remove|status|status-all)$ ]]; then
	echo "Error: invalid action \"$ACTION\"" | tee -a $LOG 1>&2;
	exit 1;
fi;

# Validate IPv4 address
if [[ $ACTION =~ ^(create|update)$ ]]; then
	if ! [[ $ADDR =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]]; then
		echo "Error: invalid IP address \"$ADDR\"" | tee -a $LOG 1>&2;
		exit 2;
	fi;
fi;

# Validate subdomain
if [[ $ACTION =~ ^(create|update|remove|status)$ ]]; then
	if ! [[ $NAME =~ ^[a-z0-9][a-z0-9-]*$ ]]; then
		echo "Error: invalid subdomain address \"$NAME\"" | tee -a $LOG 1>&2;
		exit 3;
	fi;
	NAME=$NAME.$BASEDOMAIN
fi;

# Get seconds past the epoch
NOW="`date +%s`";

# Prepare to build the query
QUERY="";

# Update powerdns record in database (should probably use parametrised query)
case "$ACTION" in
	"create")
		QUERY='INSERT INTO powerdns.records (name, type, domain_id, prio) VALUES ("'$NAME'", "A", '$DOMAIN_ID', 0);'$'\n';
		;&
	"update")
		QUERY+='UPDATE powerdns.records SET content="'$ADDR'", ttl=60, change_date='$NOW' WHERE (name="'$NAME'" AND type="A");'$'\n';
		QUERY+='SELECT ROW_COUNT();';
		;;
	"remove")
		QUERY='DELETE FROM powerdns.records WHERE (name="'$NAME'" AND type="'A'");'$'\n';
		QUERY+='SELECT ROW_COUNT();';
		;;
	"status")
		QUERY='SELECT name, content, ttl FROM powerdns.records WHERE (name="'$NAME'" AND type="A");';
		;;
	"status-all")
		QUERY='SELECT name, content, ttl FROM powerdns.records WHERE (name LIKE "%.d.%" AND name LIKE "%.co.uk") ORDER BY name ASC;';
		;;
esac;

# Trim trailing newline from query
QUERY=$( echo "$QUERY" | perl -pe 'chomp if eof;' | cat );

# Output the query to stderr and to the log
#echo "$(tput setaf 2)$QUERY$(tput sgr0)" 1>&2;
echo "Query: $QUERY">>$LOG;

# Execute the query
QUERY_RESULT=`mysql --user=$POWERDNS_USER --password=$POWERDNS_PASSWORD --batch --raw --skip-column-names --execute="$QUERY"`;

# Check result
MYSQL_RET=$?;
if [ $MYSQL_RET -ne 0 ]; then
	echo "Error: mysql returned error code $MYQSL_RET" | tee -a $LOG 1>&2;
	exit 5;
fi;

# Trim trailing newline from result
QUERY_RESULT=$( echo "$QUERY_RESULT" | perl -pe 'chomp if eof;' | cat );

# Is the result non-empty?
if [ "$QUERY_RESULT" != "" ]; then
	# Output non-empty mysql result to log
	echo "Result: $QUERY_RESULT">>$LOG;
	# Output the mysql result to stdout
	echo "$QUERY_RESULT">&1;
fi;

# Return success
exit 0;
