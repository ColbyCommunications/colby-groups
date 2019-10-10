const path = require('path');
const fs = require('fs');
// const webpack = require('webpack');

// eslint-disable-next-line no-underscore-dangle
const _includes = require('lodash/includes');
const autoprefixer = require('autoprefixer');
// const { BundleAnalyzerPlugin } = require('webpack-bundle-analyzer');
const WebpackNotifierPlugin = require('webpack-notifier');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const portFinderSync = require('portfinder-sync');

const devServerPort = portFinderSync.getPort(9000);
const isDevServer = process.argv.find(v => _includes(v, 'webpack-dev-server'));
const devServerHost = '0.0.0.0';
const currentDir = path.resolve(process.cwd());

const postcssLoader = {
    loader: 'postcss-loader',
    options: {
        plugins: () => [autoprefixer()],
    },
};

const config = {
    mode: 'development',
    entry: {
        plugin: `${currentDir}/src`,
    },
    output: {
        path: path.resolve(__dirname, 'dist'),
        filename: 'plugin.[hash].bundle.js',
        chunkFilename: '[id].plugin.[chunkhash].bundle.js',
    },
    stats: {
        all: false,
        timings: true,
        assets: true,
        excludeAssets: name => /\.map$/.test(name),
        errors: true,
        warnings: true,
    },
    module: {
        rules: [
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
                    'style-loader',
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
            {
                test: /\.(png|svg|jpg|gif)$/,
                use: ['file-loader'],
            },
        ],
    },
    plugins: [
        // Clean build folder
        new CleanWebpackPlugin(),
        new WebpackNotifierPlugin(),

        // Save app bundle filename in temp file
        // eslint-disable-next-line func-names, space-before-function-paren
        function() {
            this.plugin('done', stats => {
                let jsBundleFilename = stats.toJson({
                    all: false,
                    assets: true,
                }).assetsByChunkName.plugin;

                if (typeof jsBundleFilename !== 'string') {
                    [jsBundleFilename] = jsBundleFilename;
                }

                if (isDevServer) {
                    jsBundleFilename = `https://${devServerHost}:${devServerPort}/${jsBundleFilename}`;
                }

                // Write JS bundle
                const jsFilename = path.join(currentDir, 'build', 'js.bundle.filename');

                const fileExists = fs.existsSync(jsFilename);

                fs.writeFileSync(jsFilename, jsBundleFilename);
                if (!fileExists) {
                    fs.chmodSync(jsFilename, 0o777);
                }

                // Remove CSS bundle. We'll handle it from JS
                const cssFilename = path.join(currentDir, 'build', 'css.bundle.filename');
                if (fs.existsSync(cssFilename)) {
                    fs.unlinkSync(cssFilename);
                }
            });
        },
    ],
    devServer: {
        host: devServerHost,
        contentBase: false,
        port: devServerPort,
        clientLogLevel: 'none',
        disableHostCheck: true,
        watchOptions: {
            poll: true,
        },
        hot: false,
        overlay: true,
        https: true,
        stats: {
            all: false,
            timings: true,
            assets: true,
            excludeAssets: name => !/\.bundle\.js$/.test(name),
            errors: true,
            warnings: true,
        },
        historyApiFallback: true,
        headers: {
            'Access-Control-Allow-Origin': '*',
        },
    },
};

module.exports = config;
