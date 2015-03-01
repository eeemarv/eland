#! /bin/bash
#/bin/bash ./build/scripts/deploy.sh dev $BUILD_NUMBER $WORKSPACE

ENV=$1
BUILDNO=$2
WORKSPACE=$3
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

echo "== Running eLAS deployer --"
echo "Deploying build $BUILDNO for environment $ENV from $WORKSPACE at rev $REVNO"

function deploy {
	#deploy $USER $HOST $PORT $WORKSPACE $TARGET $ENV
	USER=$1
	HOST=$2
	PORT=$3
	WORKSPACE=$4
	TARGET=$5
	ENV=$6
	echo "Running"
	echo "rsync -rlDvzd -e 'ssh -p $PORT' --exclude=.bzr --exclude=revno --exclude=build --exclude=sites ${WORKSPACE} ${USER}@${HOST}:${TARGET}/"
	rsync -rlDvzd -e "ssh -p $PORT" --exclude=.bzr --exclude=revno --exclude=build --exclude=sites $WORKSPACE/ ${USER}@${HOST}:${TARGET}/
}

case "$ENV" in
	dev)
		echo "Deploying $VERSION to development"
		USER=sitedeployer
		HOST=r2d2.internal.taurix.net
		PORT=22
		TARGET=/nfs/www/elasdev

		deploy $USER $HOST $PORT $WORKSPACE $TARGET $ENV
		;;
	test)
		echo "Deploying $VERSION to test"
		USER=sitedeployer
		HOST=r2d2.internal.taurix.net
		PORT=22
		TARGET=/nfs/www/elastest
		
		deploy $USER $HOST $PORT $WORKSPACE $TARGET $ENV
        ;;      
	prod)
		echo "Deploying $VERSION to production"
                USER=sitedeployer
				HOST=r2d2.internal.taurix.net
				PORT=22
				TARGET=/nfs/www/elas
		
                deploy $USER $HOST $PORT $WORKSPACE $TARGET $ENV
                ;;
esac

