FROM openjdk:8u181-jre-alpine3.8

RUN apk add --no-cache \
        bash \
        ca-certificates \
        tar \
        wget \
        tzdata \
        tini \
        xmlstarlet

WORKDIR /camunda

ENTRYPOINT ["./docker/entrypoint.sh"]
