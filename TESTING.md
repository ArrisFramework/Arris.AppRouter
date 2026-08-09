# NGINX VHost:

```
server {
    listen 80;
    server_name router.local;

    root /var/www.arris/Arris.AppRouter/;

    index test_routing.php;

    access_log /var/www.arris/Arris.AppRouter/~access.log;
    error_log /var/www.arris/Arris.AppRouter/~error.log;

    location / {
        try_files $uri $uri/ /test_routing.php?$query_string;
    }

    location ~ \.php$ {
        include         fastcgi_params;
        fastcgi_param   SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass    php-handler-8;
        fastcgi_index   index.php;
    }

    location ~ favicon.* {
        access_log      off;
        log_not_found   off;
    }
}
```