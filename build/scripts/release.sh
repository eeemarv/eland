#! /bin/bash

BUILDNO=$1
WORKSPACE=$2
REVNO=`bzr revno --tree`
VERSION=`sed -n -e 's/.*<version>\(.*\)<\/version>.*/\1/p' release.xml`
SCHEMAVERSION=`sed -n -e 's/.*<schemaversion>\(.*\)<\/schemaversion>.*/\1/p' release.xml`

# Re-generate release file
cd $WORKSPACE
echo -e "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n\n<elas>" > release.xml
echo -e "\t<version>${VERSION}</version>" >> release.xml
echo -e "\t<schemaversion>${SCHEMAVERSION}</schemaversion>" >> release.xml
echo -e "\t<suffix></suffix>" >> release.xml
echo -e "\t<branch>main</branch>" >> release.xml
echo -e "\t<revision>${REVNO}</revision>" >> release.xml
echo -e "\t<build>${BUILDNO}</build>" >> release.xml
echo -e "</elas>\n" >> release.xml

echo "== Running eLAS release --"
FILENAME=elas-${VERSION}.tar.gz

if [ ! -f ${FILENAME} ]; then
    echo "<!-- Insert Piwik tracking code here -->" > $WORKSPACE/includes/inc_piwik.php
	cd $WORKSPACE && tar --exclude='build' --exclude='.bzr' -czf /nas/code/public/elas/release/elas-${VERSION}.tar.gz *
else 
	echo "File already exists for this release"
fi

