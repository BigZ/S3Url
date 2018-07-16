S3 Url
-------------------------------------------------------------------------------------------------------

Subscribe to JSON encoding event in jms serializer
Decorate with an s3 url the parameters marked with the @uploadedfile annotation

# Usage

```
    app.s3_public_url_subscriber:
        public: true
        class: S3Url\Subscriber\S3PublicUrlSubscriber
        arguments: ['@annotations.cached_reader', "%env(AWS_REGION)%", "%env(AWS_BUCKET)%"]
        tags:
            - { name: jms_serializer.event_subscriber }
```