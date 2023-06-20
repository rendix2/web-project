const path = require('path');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");

module.exports = (env, args) => {
    const isProduction = args.mode === 'production';

    return {
        context: path.resolve(__dirname),
        entry: {
            app: './scripts/app.js'
        },
        output: {
            path: path.join(__dirname, './dist'),
            publicPath: isProduction ? '/dist/' : '/',
        },
        module: {
            rules: [
                {
                    test: /\.css$/,
                    use: [
                        MiniCssExtractPlugin.loader,
                        'css-loader'
                    ],
                },
/*                {
                    test: /\.js$/,
                    use: 'js-loader'
                }*/
            ]
        },
        plugins: [
            new MiniCssExtractPlugin(),
        ],
        devServer: {
            contentBase: path.join(__dirname, 'www/dist'),
            port: 3060,
        },
    };
};