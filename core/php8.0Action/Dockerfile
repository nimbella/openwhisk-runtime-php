#
# Licensed to the Apache Software Foundation (ASF) under one or more
# contributor license agreements.  See the NOTICE file distributed with
# this work for additional information regarding copyright ownership.
# The ASF licenses this file to You under the Apache License, Version 2.0
# (the "License"); you may not use this file except in compliance with
# the License.  You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#

# build go proxy from source
ARG GO_PROXY_BASE_IMAGE=golang:1.18
FROM $GO_PROXY_BASE_IMAGE AS builder

ARG GO_PROXY_GITHUB_USER=nimbella-corp
ARG GO_PROXY_GITHUB_BRANCH=dev
RUN git clone --branch ${GO_PROXY_GITHUB_BRANCH} https://github.com/${GO_PROXY_GITHUB_USER}/openwhisk-runtime-go /src \
  && cd /src \
  && env GO111MODULE=on CGO_ENABLED=0 go build -o /bin/proxy main/proxy.go

FROM php:8.0-cli-buster

COPY php.ini /usr/local/etc/php

# install PHP extensions
RUN curl -sSLf -o /usr/local/bin/install-php-extensions \
    https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
  && chmod +x /usr/local/bin/install-php-extensions \
  && install-php-extensions bcmath gd intl mysqli opcache pdo_mysql pdo_pgsql soap zip @composer

# install default Composer dependencies
COPY composer.json /phpAction/composer/composer.json
RUN cd /phpAction/composer \
  && composer install --no-plugins --no-scripts --prefer-dist --no-dev -o \
  && rm composer.lock

# install nim
ARG NIM_INSTALL_SCRIPT=https://apigcp.nimbella.io/downloads/nim/nim-install-linux.sh
SHELL ["/bin/bash", "-o", "pipefail", "-c"]
RUN curl ${NIM_INSTALL_SCRIPT} | bash

# install proxy binary along with compile and launcher scripts
RUN mkdir -p /phpAction/action
WORKDIR /phpAction
COPY compile.php /bin/compile.php
COPY runner.php /bin/runner.php
ENV OW_COMPILER=/bin/compile.php

COPY --from=builder /bin/proxy /bin/proxy
ENTRYPOINT [ "/bin/proxy" ]
