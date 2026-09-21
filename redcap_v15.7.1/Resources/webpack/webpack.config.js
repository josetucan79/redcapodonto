const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');

module.exports = {
  entry: {
    main: ["babel-polyfill", './src/app.js']
  },
  output: {
    filename: 'bundle.js',
    path: path.resolve(__dirname, './js')
  },
  resolve: {
    extensions: [".js", ".jsx", ".ts"],
    preferRelative: true
  },
  plugins: [
    new MiniCssExtractPlugin({
      // Options similar to the same options in webpackOptions.output
      // both options are optional
      filename: '../css/bundle.css',
      chunkFilename: '../css/bundle.css',
    }),
    // Copy various files from packages into Resources/webpack/ subdirectories
    new CopyWebpackPlugin([
      // PopperJS
      {
        from: 'node_modules/@popperjs/core/dist/umd/popper.min.js',
        to: '../js/popper.min.js',
        toType: 'file'
      },
      {
        from: 'node_modules/@popperjs/core/dist/umd/popper.min.js.map',
        to: '../js/popper.min.js.map',
        toType: 'file'
      },
      // Bootstrap
      {
        from: 'node_modules/bootstrap/dist/css/bootstrap.min.css',
        to: '../css/bootstrap.min.css',
        toType: 'file'
      },
      {
        from: 'node_modules/bootstrap/dist/css/bootstrap.min.css.map',
        to: '../css/bootstrap.min.css.map',
        toType: 'file'
      },
      {
        from: 'node_modules/bootstrap/dist/js/bootstrap.min.js',
        to: '../js/bootstrap.min.js',
        toType: 'file'
      },
      {
        from: 'node_modules/bootstrap/dist/js/bootstrap.min.js.map',
        to: '../js/bootstrap.min.js.map',
        toType: 'file'
      },
      // FontAwesome CSS and Webfonts
      {
        from: 'node_modules/@fortawesome/fontawesome-free/css/all.min.css',
        to: '../css/fontawesome/css/all.min.css',
        toType: 'file'
      },
      {
        from: 'node_modules/@fortawesome/fontawesome-free/webfonts/',
        to: '../css/fontawesome/webfonts/'
      },
      // TinyMCE
      {
        from: 'node_modules/tinymce/',
        to: '../css/tinymce/',
        ignore: ['tinymce.js', 'jquery.tinymce.js', 'jquery.tinymce.min.js', 'bower.json', 'changelog.txt', 'composer.json', 'package.json', 'readme.md']
      },
      // tippy.js
      {
        from: 'node_modules/tippy.js/dist/tippy-bundle.umd.min.js',
        to: '../js/tippyjs/tippy.js'
      },
      {
        from: 'node_modules/tippy.js/dist/tippy.css',
        to: '../css/tippyjs/tippy.css'
      },
      // moment.js
      {
        from: 'node_modules/moment/min/moment.min.js',
        to: '../js/moment.min.js'
      },
      {
        from: 'node_modules/moment/min/moment.min.js.map',
        to: '../js/moment.min.js.map'
      },
      // DataTables
      {
        from: 'node_modules/datatables.net-dt/css/',
        to: '../css/datatables/',
        ignore: ['jquery.dataTables.css']
      },
      // PDFObject
      {
        from: 'node_modules/pdfobject/',
        to: '../js/pdfobject/'
      }
    ])
  ],
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: {
          loader: "babel-loader"
        }
      },
	  {
		test: require.resolve('jquery'),
		use: [{
		  loader: 'expose-loader',
		  options: 'jQuery'
		},{
		  loader: 'expose-loader',
		  options: '$'
		}]
	  },
      {
        test: /\.(css|sass|scss)$/,
        use: [
          MiniCssExtractPlugin.loader,
          {
            loader: 'css-loader',
            options: {
              importLoaders: 2,
              sourceMap: true
            }
          },
          {
            loader: 'postcss-loader',
            options: {
              plugins: () => [
                require('autoprefixer')
              ],
              sourceMap: true
            }
          },
          {
            loader: 'sass-loader',
            options: {
              sourceMap: true
            }
          }
        ]
      },
      {
        test: /\.(jpe?g|png|gif)$/i,
        loader:"file-loader",
        options:{
          name:'[name].[ext]',
          outputPath: '../images/',
          publicPath: 'Resources/webpack/images/'
        }
      },
      {
        test: require.resolve('sweetalert2'),
        use: [{
          loader: 'expose-loader',
          options: 'Swal'
        }]
      }
    ]
  }
};