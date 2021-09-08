const path = require("path");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
// const OptimizeCSSAssetsPlugin = require("optimize-css-assets-webpack-plugin");
const CopyWebpackPlugin = require('copy-webpack-plugin');
const mode = process.env.NODE_ENV || "development";
const minimize = mode === "production";
const plugins = [];

if (mode === "production") {
  // plugins.push(
  //   new OptimizeCSSAssetsPlugin({
  //     cssProcessorOptions: {
  //       discardComments: true
  //     }
  //   })
  // );
}

module.exports = {
  mode,
  devtool: "source-map",
  entry: [
    path.resolve(__dirname, "index.js"),
    path.resolve(__dirname, "index.scss")
  ],
  externals: {
    osjs: "OSjs",
    oxziongui: "oxziongui"
  },
  optimization: {
    minimize
  },
  plugins: [
    new CopyWebpackPlugin([
      { from: 'public/img', to: 'img' },
      "icon.svg",
      "icon_white.svg"
    ]),
    new MiniCssExtractPlugin({
      filename: "[name].css",
      chunkFilename: "[id].css"
    }),
    ...plugins
  ],
  module: {
    rules: [
      {
        test: /\.(svg|png|jpe?g|gif|webp|)$/,
        use: [
          {
            loader: "file-loader",
            options: {
              name: "[name].[hash].[ext]",
              outputPath: "images"
            }
          }
        ]
      },
      {
        test: /\.(html)$/,
        use: {
          loader: 'html-loader',
          options: {
            attrs: [':data-src']
          }
        }
      },
      {
        test: /\.(woff(2)?|ttf|eot)(\?v=[0-9]\.[0-9]\.[0-9])?$/,
        loader: "file-loader",
        options: {
          name: "[name].[hash].[ext]",
          outputPath: "font"
        }
      },
      {
        test: /\.(sa|sc|c)ss$/,
        use: [
          MiniCssExtractPlugin.loader,
          'css-loader',
          'sass-loader',
        ],
        sideEffects: true,
      },
      {
        test: /\.js$/,
        exclude: /(node_modules|bower_components)/,
        include: /(node_modules\/\@oxzion|.)/,
        use: {
          loader: "babel-loader",
          options: {
            presets: [
              '@babel/react', '@babel/env'
            ],
            plugins: [
              require.resolve("@babel/plugin-transform-runtime"),
              '@babel/proposal-class-properties'
            ]
          }
        }
      }
    ]
  }
};
