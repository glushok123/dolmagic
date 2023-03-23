## Требования

Для запуска приложения требуется:

* docker `>=18.06`
* docker-compose `>=1.24`

## Установка (на машине разработчика)

Сборка приложения:

```bash
# скопировать файл окружения
cp ./docker/development/docker.env.dist ./docker.env

# скопировать конфигурационные файлы приложения
cp ./config/default/* ./config

# выполнить сборку контейнеров
make build

# установить php-зависимости
make composer-install

# установить NodeJs зависимости
make npm-install

# запустить приложение
make up

# скопировать дамп базы данных в директорию ./docker/dumps в формате gzip
cp <директория_дамп_файла>/<имя_дамп_файла.gz> ./docker/dumps

# восстановить дамп базы данных
make restore-db-gzip filename="<имя_дамп_файла.gz>" password="secret"

# выполнить подготовительные действия в приложении
make init

```

Запуск приложения:

```bash
make up
```
Подробнее о [make командах](#make-desc)

## Использование

* [http://localhost:8081](http://localhost:8081)

## <a name="make-desc"></a>Make команды
```bash
make up # инициализация контейнеров
make down # завершение контейнеров
make restart # перезапуск контейнеров
make build # сборка образов
make ps # состояние контейнеров
make init # выполнение подготовительных действия в приложении
make docker-build-test-nginx # собрать образ nginx
make docker-build-test-php-fpm # собрать образ php-fpm
make docker-build-test-php-cli # собрать образ php-cli
make php-cli args="<аргументы>" # выполнить команду в контейнере php-cli
make artisan-run cmd="<команда>" # выполнить artisan команду в проекте
make composer-install args="<аргументы>" # установить php зависимости
make composer run="<тип_выполнения>" # запуск менеджера зависимостей php
make node # запуск обработчика NodeJs
make npm-install # установить NodeJs зависимости
make npm-build # выполнить сборку файлов NodeJs
make npm-watch # запустить сборке файлов NodeJs в режиме отслеживания
make npm run="<тип_выполнения>" # запуск менеджера зависимостей NodeJs
make dump-db password="root_пароль" options="-v" # создать дамп базы данных
make restore-db-gzip filename="<имя_дамп_файла.gz>" password="root_пароль" # восстановить дамп базы данных
```