const path = require('path');
const mode = process.env.NODE_ENV || 'development';
const minimize = mode === 'production';
const webpack = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const {
  DefinePlugin
} = webpack;
const OptimizeCSSAssetsPlugin = require('optimize-css-assets-webpack-plugin');
const npm = require('./package.json');
const plugins = [];

if (mode === 'production') {
  plugins.push(new OptimizeCSSAssetsPlugin({
    cssProcessorOptions: {
      discardComments: true,
      map: {
        inline: false
      }
    },
  }));
}

module.exports = {
  mode: (mode !== 'development' ? 'production' : mode),
  devtool: 'source-map',
  entry: {
    osjs: [
      path.resolve(__dirname, 'src/core/core.js'),
      path.resolve(__dirname, 'src/client/index.js'),
      path.resolve(__dirname, 'src/client/assets/scss/index.scss')
    ]
  },
  performance: {
    maxEntrypointSize: 500 * 1024,
    maxAssetSize: 500 * 1024
  },
  optimization: {
    minimize,
    splitChunks: {
      chunks: 'all'
    }
  },
  plugins: [
    new DefinePlugin({
      OSJS_VERSION: npm.version
    }),
    new CopyWebpackPlugin([
      'src/client/assets/images/load.svg',
      './ViewerJS'
    ]),
    new HtmlWebpackPlugin({
      template: path.resolve(__dirname, 'src/client/index.ejs'),
      favicon: path.resolve(__dirname, 'src/client/favicon.ico'),
      title: 'OX Zion'
    }),
    new MiniCssExtractPlugin({
      filename: '[name].css'
    }),
    ...plugins
  ],
  module: {
    rules: [{
        test: /\.(svg|png|jpe?g|gif|webp)$/,
        use: [{
          loader: 'file-loader'
        }]
      },
      {
        test: /\.(ttf|eot|svg)(\?v=[0-9]\.[0-9]\.[0-9])?$/,
        use: {
          loader: 'file-loader',

        }
      },
      {
        test: /\.woff(2)?(\?v=[0-9]\.[0-9]\.[0-9])?$/,
        loader: "url-loader?limit=10000&mimetype=application/font-woff"
      },
      {
        test: /\.(sa|sc|c)ss$/,
        use: [
          MiniCssExtractPlugin.loader,
          {
            loader: 'css-loader',
            options: {
              sourceMap: true
            }
          },
          {
            loader: 'sass-loader',
            options: {
              minimize,
              sourceMap: true
            }
          },
        ]
      }
    ]
  }
};
