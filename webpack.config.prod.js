const fs = require('fs');
const path = require('path');
const webpack = require('webpack');
const autoprefixer = require('autoprefixer');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const { BundleAnalyzerPlugin } = require('webpack-bundle-analyzer');
const UglifyJsPlugin = require('uglifyjs-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const OptimizeCSSAssetsPlugin = require('optimize-css-assets-webpack-plugin');

const currentDir = path.resolve(process.cwd());

const postcssLoader = {
    loader: 'postcss-loader',
    options: {
        ident: 'postcss',
        plugins: () => [
            autoprefixer({
                browsers: ['> 1%', 'last 2 versions', 'Firefox ESR'],
            }),
        ],
    },
};

const config = {
    mode: 'production',
    entry: {
        plugin: `${currentDir}/src`,
    },
    output: {
        path: path.resolve(__dirname, 'dist'),
        filename: 'plugin.[hash].bundle.js',
        chunkFilename: '[id].plugin.[chunkhash].bundle.js',
    },
    module: {
        rules: [
            {
                test: /\.(jpg|png|gif|svg)$/,
                use: {
                    loader: 'url-loader',
                    options: {
                        limit: 5000,
                        name: '[name]-[hash].[ext]',
                    },
                },
            },
            {
                test: /\.(ttf|eot|woff|woff2|mp3)$/,
                use: ['file-loader'],
            },
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: [
                    {
                        loader: 'babel-loader',
                        options: {
                            cacheDirectory: true,
                            babelrc: false,
                            presets: [require.resolve('babel-preset-colby')],
                        },
                    },
                ],
            },
            {
                test: /\.scss$/,
                use: [
                    MiniCssExtractPlugin.loader,
                    {
                        loader: 'css-loader',
                        options: {
                            importLoaders: 2,
                            modules: {
                                context: currentDir,
                                localIdentName: '[local]__[hash:base64:5]',
                            },
                        },
                    },
                    'sass-loader',
                    postcssLoader,
                ],
            },
        ],
    },
    plugins: [
        // Clean build folder
        new CleanWebpackPlugin(),

        new MiniCssExtractPlugin({
            filename: 'plugin.[contenthash].bundle.css',
        }),

        new webpack.optimize.MinChunkSizePlugin({
            minChunkSize: 50000,
        }),

        // Save app bundle filename in temp file
        // eslint-disable-next-line func-names, space-before-function-paren
        function() {
            this.plugin('done', stats => {
                const chunks = stats.toJson({ all: false, assets: true }).assetsByChunkName.plugin;
                let jsBundleFilename = chunks.find(file => /^plugin.*\.js$/.test(file));

                let cssBundleFilename = chunks.find(file => /^plugin.*\.css$/.test(file));

                cssBundleFilename = `dist/${cssBundleFilename}`;
                jsBundleFilename = `dist/${jsBundleFilename}`;

                // Write js bundle
                (() => {
                    const filename = path.join(currentDir, 'build', 'js.bundle.filename');
                    const fileExists = fs.existsSync(filename);
                    fs.writeFileSync(filename, jsBundleFilename);
                    if (!fileExists) {
                        fs.chmodSync(filename, 0o777);
                    }
                })();

                // Write css bundle
                (() => {
                    const filename = path.join(currentDir, 'build', 'css.bundle.filename');
                    const fileExists = fs.existsSync(filename);
                    fs.writeFileSync(filename, cssBundleFilename);
                    if (!fileExists) {
                        fs.chmodSync(filename, 0o777);
                    }
                })();
            });
        },
    ],
    devtool: process.env.ANALYZE_BUNDLE ? false : 'source-map',
    optimization: {
        noEmitOnErrors: true,
        minimizer: [
            new UglifyJsPlugin({
                cache: true,
                parallel: true,
                sourceMap: true,
                uglifyOptions: {
                    mangle: { keep_fnames: true },
                },
            }),
            new OptimizeCSSAssetsPlugin({}),
        ],
    },
    stats: {
        all: false,
        timings: true,
        assets: true,
        excludeAssets: name => /\.map$/.test(name),
        errors: true,
        warnings: true,
    },
};

if (process.env.ANALYZE_BUNDLE) {
    config.plugins.push(new BundleAnalyzerPlugin({ defaultSizes: 'gzip' }));
}

module.exports = config;
