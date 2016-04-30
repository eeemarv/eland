### Using Gitbook

Pull first the latest version from master.
```shell
git pull origin master
```

Get gitbook-cli in your local repo:


```shell
npm update --save-dev
```

The documentation is in the docs directory.  

It can be served at localhost:4000 with

```shell
npm run docs:watch
```

Build static html and publish [online](http://letsa.net) (pushing to the gh-pages branch)
```shell
npm run docs:publish
```

Commit and push the changes you made to the documentation. These are in the master branch. The gh-pages branch is only used for publishing the static files and is not primary data.
```shell
git commit -a -m 'edit to the docs'
git push origin master
```


Ref:

[Using Gitbook to document an open source project](https://medium.com/@gpbl/how-to-use-gitbook-to-publish-docs-for-your-open-source-npm-packages-465dd8d5bfba)

[Gitbook](https://github.com/GitbookIO/gitbook)
