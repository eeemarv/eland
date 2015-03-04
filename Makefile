VERSION = `sed -n -e 's/.*<version>\(.*\)<\/version>.*/\1/p' release.xml`
FILENAME = "/tmp/elas-${VERSION}.tar.gz"

release : clean files checksum tarball
	@echo "Generating release ${VERSION}";

checksum :
	@cd /tmp/elas && find ./ -type f -exec sha1sum "{}" ";" >../SHA1SUMS
	@cd /tmp/elas && mv ../SHA1SUMS .

sign :
	@cd /tmp/elas && gpg --sign SHA1SUMS

files :
	@bzr export /tmp/elas
	@cp release.xml /tmp

tarball :
	echo "Creating version ${VERSION} as ${FILENAME}"
	@cd /tmp && tar -czf ${FILENAME} --exclude=update-rsync.sh --exclude=.bzr --exclude=.be --exclude=Makefile --exclude=build elas

clean :
	-rm -rf /tmp/elas
	-rm ${FILENAME}
	-rm /tmp/release.xml
