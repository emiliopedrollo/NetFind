# NetFind

## Meios de execução

Há diversas formas de executar este programa:

### Via pacote de distribuição

```
builds/netfind
```

> Nota: Ainda é necessário ter PHP 7.3+ instalado para executar desta forma

### Via script PHP

```
php netfind
```

### Via docker-compose 

```shell
docker-compose run --rm netfind
```

### Via docker

```shell script
docker build --tag netfind .
docker run -it --rm --network host --privileged -v /var/run/dbus:/var/run/dbus netfind
```

## Ajuda

Existe uma tela de ajuda com detalhes de argumentos e opções aceitas pelo programa:

```shell script
builds/netfind --help
```

A ajuda está disponível em todas as diversas maneiras de executar o programa, basta 
acrescentar `--help`.

```text
Description:
  Discover network devices

Usage:
  netfind [options] [--] [<device>...]

Arguments:
  device                A space separated list of network devices to discover

Options:
  -d, --delay=DELAY     Time in milliseconds between discoveries [default: 1000]
  -h, --help            Display this help message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
      --env[=ENV]       The environment the command should run under
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

> Nota: Usando Docker é necessário executar o build da imagem antes que o help esteja disponível.

## Código

A parte interessante do código este no arquivo [DiscoverCommand.php](app/Commands/DiscoverCommand.php), 
mais especificamente na função `handle`.
