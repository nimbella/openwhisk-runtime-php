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
FROM $GO_PROXY_BASE_IMAGE AS builder_source
ARG GO_PROXY_GITHUB_USER=nimbella-corp
ARG GO_PROXY_GITHUB_BRANCH=dev
RUN git clone --branch ${GO_PROXY_GITHUB_BRANCH} \
  https://github.com/${GO_PROXY_GITHUB_USER}/openwhisk-runtime-go /src ;\
  cd /src ; env GO111MODULE=on CGO_ENABLED=0 go build main/proxy.go && \
  mv proxy /bin/proxy

# or build it from a release
FROM $GO_PROXY_BASE_IMAGE AS builder_release
ARG GO_PROXY_RELEASE_VERSION=1.15@1.17.0
RUN curl -sL \
  https://github.com/apache/openwhisk-runtime-go/archive/{$GO_PROXY_RELEASE_VERSION}.tar.gz\
  | tar xzf -\
  && cd openwhisk-runtime-go-*/main\
  && GO111MODULE=on go build -o /bin/proxy

FROM php:8.2-cli-bullseye

# select the builder to use
ARG GO_PROXY_BUILD_FROM=source

COPY php.ini /usr/local/etc/php

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    # install Python to allow the builder to run correctly.
    python \
  && rm -rf /var/lib/apt/lists/*

# install PHP extensions
RUN curl -sSLf -o /usr/local/bin/install-php-extensions \
    https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
  && chmod +x /usr/local/bin/install-php-extensions \
  && install-php-extensions \
    @composer \
    bcmath \
    gd \
    intl \
    mongodb \
    mysqli \
    opcache \
    pdo_mysql \
    pdo_pgsql \
    soap \
    zip

# install default Composer dependencies
COPY composer.json /phpAction/composer/composer.json
RUN cd /phpAction/composer \
  && composer install --no-plugins --no-scripts --prefer-dist --no-dev -o \
  && rm composer.lock

# install the functions-deployer
ARG DEPLOYER_DOWNLOAD
SHELL ["/bin/bash", "-o", "pipefail", "-c"]
RUN curl -L ${DEPLOYER_DOWNLOAD} | tar xzf - \
  && rm -fr /usr/local/lib/dosls && mv dosls /usr/local/lib \
  && rm -f /usr/local/bin/dosls && ln -s /usr/local/lib/dosls/bootstrap /usr/local/bin/dosls

# install proxy binary along with compile and launcher scripts
RUN mkdir -p /phpAction/action
WORKDIR /phpAction
COPY --from=builder_source /bin/proxy /bin/proxy_source
COPY --from=builder_release /bin/proxy /bin/proxy_release
RUN mv /bin/proxy_${GO_PROXY_BUILD_FROM} /bin/proxy
COPY compile.php /bin/compile.php
COPY runner.php /bin/runner.php
ENV OW_COMPILER=/bin/compile.php

# the launcher must wait for an ack
ENV OW_WAIT_FOR_ACK=1

ENTRYPOINT [ "/bin/proxy" ]
