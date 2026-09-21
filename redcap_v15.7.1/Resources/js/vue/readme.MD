## Vue components

### Development notes
* avoid dynamic imports using `() => import('./app')` because will cause the build to split the app.js bundle (one submodule for every dynamic import). Use `require('./app').default` instead
* all build are made with CSS included in the js files (): ` css: { extract: false }`
* filename hasing is turned off in builds: `filenameHashing: false`
* sourceMaps are disabled in production: productionSourceMap: false
* no chunks are produced in production for the wrapper 'vue-factory':
```
configureWebpack: {
    optimization: {
        splitChunks: false
    }
}
```
* make sure to have consistent packages.json configuration; refer to the vue-factory packages.json
  * remember to clean yarn cache when updating packages: `yarn cache clean`
* when an object contained by a VueComponent (e.g. $API or $router) is needed in the store, pass the VueComponent in the `dispatch`method as `vm` 


## TODO
* upgrade from babel-eslint (not maintained anymore) to @babel/eslint-parser
  * fix the configuration of the dev server in vue.config.js from
    ```
    devServer: {
      overlay: {
          warnings: false,
          errors: true
      },
      ...
    }
    ```
    to
    ```
    devServer: {
      overlay: {
        client: {
          overlay: true,
        },
        ...
      }
    }
    ```
  * change the `parserOptions: parser` configuration in package.json from `babel-eslint` to `@babel/eslint-parser`