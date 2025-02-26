vcl 4.1;

import std;

# Define an ACL for allowed purge sources.
acl purge {
  "localhost";
  "192.168.0.0"/16;
}

backend default {
  .host = "web";
  .port = "80";
}

sub vcl_recv {
  # Handle PURGE requests from authorized clients.
  if (req.method == "PURGE") {
    if (!client.ip ~ purge) {
      return (synth(405, "Not allowed."));
    }
    return (purge);
  }

  # Only cache GET and HEAD requests.
  if (req.method != "GET" && req.method != "HEAD") {
    return (pass);
  }

  # Bypass caching for URLs that likely need real-time data.
  if (req.url ~ "^/(admin|user|internal)") {
    return (pass);
  }

  # Check for Drupal session cookies – if present, bypass caching.
  if (req.http.Cookie) {
    if (req.http.cookie ~ "(^|;\s*)(SESS=)") {
      return (pass);
    }
    # For anonymous users, strip cookies to improve cache hit rates.
    unset req.http.Cookie;
  }

  # Acquia simplified
  if (req.http.X-Forwarded-For) {
    set req.http.X-Forwarded-For = req.http.X-Forwarded-For + ", " + client.ip;
  } else {
    set req.http.X-Forwarded-For = client.ip;
  }

  if(req.url ~ "^/cron.php") {
    return(pass);
  }
  if(req.url ~ "^/xmlrpc.php") {
    return(pass);
  }
  if (req.http.Authorization) {
    return(pass);
  }

  return (hash);
}

sub vcl_backend_response {
  # Remove Set-Cookie header for cacheable content.
  if (beresp.http.Set-Cookie) {
    # If Drupal marks the response as private, do not cache.
    if (beresp.http.Cache-Control ~ "private") {
      return (deliver);
    }
    unset beresp.http.Set-Cookie;
  }

  # If Drupal instructs not to cache this response, deliver it directly.
  if (beresp.http.Cache-Control ~ "no-cache" || beresp.http.Pragma ~ "no-cache") {
    return (deliver);
  }

  # Enable ESI if Drupal provides a Surrogate-Control header.
  if (beresp.http.Surrogate-Control) {
    set beresp.do_esi = true;
  }

  # If no caching policy is set by Drupal, use a default.
  if (!beresp.http.Cache-Control) {
    set beresp.http.Cache-Control = "public, max-age=3600";
  }

  return (deliver);
}

sub vcl_deliver {
  # Optionally add a header to see if a response was served from cache.
  if (obj.hits > 0) {
    set resp.http.X-Cache = "HIT";
  } else {
    set resp.http.X-Cache = "MISS";
  }
}

sub vcl_synth {
  # Provide a simple message for unauthorized PURGE requests.
  if (resp.status == 405) {
    set resp.http.Content-Type = "text/plain; charset=utf-8";
    synthetic("405 Purge method is not allowed.");
    return (deliver);
  }
}
