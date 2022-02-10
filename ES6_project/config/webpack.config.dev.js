//const HtmlWebpackPlugin = require('html-webpack-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const webpack = require('webpack');
const path = require('path');

const PATHS = {
    src: path.join(__dirname, '../src'),
    dist: path.join(__dirname, '../dist')
};

let optimization = {}
if (process.env.npm_lifecycle_event === 'dev:chunk') {
    optimization = {
        /* runtimeChunk: 'single', */
        splitChunks: {
            cacheGroups: {
                vendor: {
                    chunks: 'all',
                    name: 'vendor',
                    /* test: 'vendor', */
                    test: /[\\/]node_modules[\\/]/,
                    filename: 'vendors.js',
                    enforce: true,
                },
            }
        }
    }
}

module.exports = {
    context: __dirname,
    mode: 'development',
    entry: {
        app: `${PATHS.src}/attributico.js`,
    },
    output: {
        path: PATHS.dist,
        filename: 'attributico.js',
        publicPath: './'
    },
    devtool: 'source-map',
    optimization: optimization,
    resolve: {
        extensions: ['.js', '.jsx', '.jsm', '.ts', '.tsx'],
        alias: {
            styles: path.resolve(__dirname, '../src/styles')
            /* 'react-dom': path.resolve(path.join(__dirname, './node_modules/@hot-loader/react-dom')),
            'react-dom': '@hot-loader/react-dom' */
        }
    },
    module: {
        rules: [
            {
                test: /\.css$/,
                use: [
                    { loader: 'style-loader' },
                    /*  MiniCssExtractPlugin.loader, */
                    { loader: 'css-loader?url=false', options: { modules: true, sourceMap: true } },
                    {
                        loader: 'postcss-loader',
                        /* options: {
                            postcssOptions: {
                                plugins: [
                                    [
                                        'postcss-preset-env',
                                        {
                                            // Options
                                        },
                                    ],
                                ],
                            },
                        }, */
                    },
                    /* { loader: 'resolve-url-loader', options: { sourceMap: true, removeCR: true } }, */
                ]
            },
            {
                test: /\.s[ac]ss$/i,
                use: [
                    // Creates `style` nodes from JS strings
                    { loader: 'style-loader' },
                    // Translates CSS into CommonJS
                    { loader: 'css-loader', options: { sourceMap: true } },
                    // Compiles Sass to CSS
                    { loader: 'sass-loader', options: { sourceMap: true } },
                ],
            },
            {
                test: /.jsx?$/,
                exclude: /node_modules/,
                loader: 'babel-loader',
                options: {
                    'plugins': [
                        ['@babel/plugin-proposal-decorators', { 'legacy': true }],
                        ['@babel/plugin-proposal-class-properties', { 'loose': false }]
                    ],
                    compact: true    // or false during development
                }
            },
            {
                test: /\.(jpg|png|gif)$/,
                use: 'file-loader'
            },
            {
                test: /\.tsx?$/,
                use: 'ts-loader',
                exclude: /node_modules/,
            },
            {
                test: /\.svg$/,
                use: ['raw-loader']
            },
            { test: /\.(png|woff|woff2|eot|ttf|svg)$/, use: ['url-loader?limit=100000'] }
        ]
    },
    plugins: [
        /* new MiniCssExtractPlugin(), */
        new webpack.DefinePlugin({
            PRODUCTION: JSON.stringify(false),
            VERSION: JSON.stringify('1.2.0'),
            DEBUG: true,
            CODE_FRAGMENT: '80 + 5'
        }),
        new webpack.ProvidePlugin({
            // Make jQuery / $ available in every module:
            $: 'jquery',
            jQuery: 'jquery',
            // NOTE: Required to load jQuery Plugins into the *global* jQuery instance:
            jquery: 'jquery',
            'window.jQuery': 'jquery'
        }),
    ],
    devServer: {
        contentBase: PATHS.dist,
        compress: true,
        headers: {
            'X-Content-Type-Options': 'nosniff',
            'X-Frame-Options': 'DENY'
        },
        open: true,
        overlay: {
            warnings: true,
            errors: true
        },
        port: 0,
        publicPath: 'http://hozmag/admin/index.php?route=module/attributico&token=YStWHt087IqtCIPUF2EbcCSgYaMfQWaZ',
        hot: true
    },
    stats: {
        children: false
    }
};
