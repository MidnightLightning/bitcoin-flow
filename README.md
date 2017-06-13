This PHP script generates an SVG graph of [Bitcoins](http://www.weusecoins.com/), starting from a given [transaction](https://en.bitcoin.it/wiki/Transaction) and working backwards, using the [Blockchain.info JSON API](http://blockchain.info/api/).

# Running

```
docker run --rm --name composer -v "$PWD":/app -w /app composer/composer update
docker build -t bitcoin-flow .
docker run -d --name bitcoin-flow -v "$PWD":/var/www/html/ -p 8081:80 bitcoin-flow
```
