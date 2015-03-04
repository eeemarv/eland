#! /bin/bash
#/bin/bash ./build/scripts/deploy.sh dev $BUILD_NUMBER $WORKSPACE

ENV=$1
BUILDNO=$2
WORKSPACE=$3
REVNO=`bzr revno --tree`
VERSION=`sed -n -e 's/.*<version>\(.*\)<\/version>.*/\1/p' release.xml`
# Set the revision information
cat release.xml | sed "s/<revision>/<revision>`echo $REVNO`/" > release.xml
# Set the build number
cat release.xml | sed "s/<build>/<build>`echo $BUILDNO`/" > release.xml

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
	rsync -avzd -e "ssh -p $PORT" $WORKSPACE --exclude=.bzr --exclude=revno --exclude=build --exclude=sites ${WORKSPACE}/ ${USER}@${HOST}:${TARGET}/
}

case "$ENV" in
	dev)
		echo "Deploying $VERSION to development"
		USER=cideployer
		HOST=firefly.vsbnet.be
		PORT=2222
		TARGET=/data/vhosts/elasdev

		deploy $USER $HOST $PORT $WORKSPACE $TARGET $ENV
		;;
	test)
		echo "Deploying $VERSION to test"
                USER=cideployer
                HOST=firefly.vsbnet.be
                PORT=2222
                TARGET=/data/vhosts/elastest
                deploy $USER $HOST $PORT $WORKSPACE $TARGET $ENV
                ;;
	prod)
		echo "Deploying $VERSION to production"
                USER=cideployer
                HOST=firefly.vsbnet.be
                PORT=2222
                TARGET=/data/vhosts/elas
                deploy $USER $HOST $PORT $WORKSPACE $TARGET $ENV
                ;;
esac
