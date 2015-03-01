#! /bin/bash
#/bin/bash ./build/scripts/deploy.sh dev $BUILD_NUMBER $WORKSPACE

BUILDNO=$1
WORKSPACE=$2
REVNO=`bzr revno --tree`
VERSION=`sed -n -e 's/.*<version>\(.*\)<\/version>.*/\1/p' release.xml`
SCHEMAVERSION=`sed -n -e 's/.*<schemaversion>\(.*\)<\/schemaversion>.*/\1/p' release.xml`

echo "Schemaversion: ${SCHEMAVERSION}"
# Set the revision information
#cat release.xml | sed "s/<revision>/<revision>`echo $REVNO`/" > release.xml
# Set the build number
#cat release.xml | sed "s/<build>/<build>`echo $BUILDNO`/" > release.xml

# Re-generate release file
echo -e "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n\n<elas>" > release.xml
echo -e "\t<version>${VERSION}</version>" >> release.xml
echo -e "\t<schemaversion>${SCHEMAVERSION}</schemaversion>" >> release.xml
echo -e "\t<suffix></suffix>" >> release.xml
echo -e "\t<branch>${ENV}</branch>" >> release.xml
echo -e "\t<revision>${REVNO}</revision>" >> release.xml
echo -e "\t<build>${BUILDNO}</build>" >> release.xml
echo -e "</elas>\n" >> release.xml

echo "== Uploading eLAS =="

USER=sitedeployer
HOST=r2d2.internal.taurix.net
PORT=22
TARGET=/nfs/releases/elas/$VERSION
LATEST=/nfs/releases/elas/LATEST

echo "Running"
echo "rsync -rlDvzd -e 'ssh -p $PORT' --exclude=.bzr --exclude=revno --exclude=build --exclude=sites ${WORKSPACE} ${USER}@${HOST}:${TARGET}/"
rsync -rlDvzd -e "ssh -p $PORT" --exclude=.bzr --exclude=revno --exclude=build --exclude=sites $WORKSPACE/ ${USER}@${HOST}:${TARGET}/

echo "Creating new symlink"
ssh -p $PORT ${USER}@${HOST} "rm $LATEST"
ssh -p $PORT ${USER}@${HOST} "ln -s $TARGET $LATEST"

