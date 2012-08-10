# Version

This document describes the _remoteStorage_ API with version 
**remoteStorage-2012.10**.

# Introduction
After gaining some experience by designing the _remoteStorage_ API as part of the
_Unhosted_ project and using well established existing protocols we feel that
it is time to let go of the specifications that are for our purposes too 
bloated. We started out by leveraging existing protocols like WebDAV, Webfinger 
and OAuth, but as it turns out these protocols are way too complex to support 
in minimalistic implementations. Those simple implementations are helpful for 
implementing the _remoteStorage_ API in embedded devices and on mobile 
platforms.

This specification will try to use as much as possible from existing standards 
like WebDAV and OAuth, but specify a subset of those protocols that are 
required for a _remoteStorage_ compatible service.

# What changed?
Earlier versions of the specifcation also included storage discovery. That is,
how to figure out where a user's storage is located. Initial versions used 
*Webfinger* to perform the discovery, but as it turns out it is nearly 
impossible to convince domain owners, especially of larger organizations to 
implement Webfinger in a meaningful way, i.e.: add _remoteStorage_ information 
and also provide CORS headers on the Webfinger endpoint. Another issue is the 
need to use user's email addresses which has some privacy concerns. Advertising 
user's email addresses in public URLs for sharing file should be avoided if 
possible. Also, the user experience while using Webfinger is a problem: the 
user needs to specify their email address for every application. This approach 
to applications has been superseded by the way modern desktop operating 
systems, mobile devices and web browsers function: provide the user with an 
application launch screen. So, Webfinger is out and instead we will define a 
"Storage First" approach that makes more sense from the user's perspective.

Previous versions specified *WebDAV* as well. *WebDAV* is an 
existing HTTP based file storage and retrieval solution that would be perfectly 
suitable for this, but is very complex to implement and has lots of 
interoperability issues. WebDAV would be the way to go if it would mean that 
desktop and mobile operating systems would be able to use _remoteStorage_ 
directly, but due to inflexible and incompatible authentication mechanisms 
provided in the operating systems this is unfeasible. There would need to be a 
separate client application anyway. So no benefit there by reusing WebDAV.

The other specification that was used was OAuth. While the protocol itself is 
very flexible, everything is possible, it is also one of its weaknesses. So,
this protocol will keep refering to OAuth, but specifies an OAuth "profile" 
containing the minimal required implemtnation details. 

The goal of this specification is to specify everything needed for a developer
to create their own _remoteStorage_ compatible service.

# remoteStorage API
The API did not change since the last versions of the specification. This 
specification however will include (non normative) examples of each of the 
calls making it easy to see what is going on exactly. The examples below are 
all without any authorization. See the next section for more information about 
authorization.

There are four HTTP verbs available in the _remoteStorage_ API:

* `GET` - retrieve a file or directory listing
* `PUT` - store a file
* `DELETE` - delete a file
* `OPTIONS` - advertizes CORS support, see section on Cross Origin Headers

A _remoteStorage_ server has a *storageRoot* URI per user, a few examples:

    https://www.example.org/remoteStorage/api/john.doe/
    https://john.example.org/
    https://rs.example.org:8042/api.php/

A user identifier does not need to be a part of the *storageRoot*, it is up
to the implementor to decide about this. The only requirements are that the 
*storageRoot* is bound to the authenticated user and that there is some way to 
have a `public` directory under this root belonging to the user.

All calls to either of the above `GET`, `PUT`, `DELETE` and `OPTIONS` MUST be 
to the *storageRoot* or directories or files under this directory, for instance:

    https://www.example.org/remoteStorage/api/john.doe/calendar/2012/10/14

## Retrieve a file or directory listing
It is possible to retrieve two things using the `GET` verb:

* Files
* Directory listings

To indicate whether or not a file or directory listing is requested the URL 
either contains a forward slash (`/`) at the end, indicating a directory 
listing is requested, or no forwarding slash, indicating a file is requested 
instead.

### Retrieving a file

#### Request

    GET /remoteStorage/api/john.doe/calendar/2012/10/14 HTTP/1.1

#### Response

    HTTP/1.1 200 OK
    Content-Type: application/json
    ETag: "d101b2fc6be2e96bed2a6f008f4308ec"

    [{"start":"09:00","end":"10:00","activity":"Shopping"},{"start":"12:00","end":"13:00","activity":"Lunch"}]

The `Content-Type` given back to the client MUST be identical to the value that
was specified while uploading the file using `PUT`, described in the section 
below. The `ETag` header SHOULD be present and it is an opaque identifier for
the content of the file, in accordance with the HTTP protocol.

If a file does not exist, a `404 Not Found` is returned: 

    HTTP/1.1 404 Not Found
    Content-Type: application/json

    {"error":"not_found","error_description":"file not found"}

### Retrieving a directory listing

#### Request

    GET /remoteStorage/api/john.doe/calendar/2012/10/ HTTP/1.1

#### Response

    HTTP/1.1 200 OK
    Content-Type: application/json

    {"14":1344251958,"16":1344249635,"23":1344027615}

For directories, the `ETag` header is usually omitted and it is not
specified how its value depends on the directory's contents.

If a directory does not exist or even if it is a file, an empty list is 
returned:

    HTTP/1.1 200 OK
    Content-Type: application/json

    {}

A directory in a file list is indicated by a forward slash (`/`) at the end of 
the name, e.g.:

    {"foo/":134427616,"bar":1344293411}

Here `foo/` denotes a directory and `bar` denotes a file.

In addition, the timpestamp of a directory MUST be identical to the most recent 
timestamp of any file contained in that directory, no matter how "deep" in the 
tree. This recurses up to the *storageRoot*. 

For example suppose the following directory structure:

    *storageRoot*/ (12300)
       foo/   (12340)
         bar/ (12340)
           foobar (12340)
           barfoo (12320)

Here `foobar` is the file with the latest timestamp, `12340`, this means also 
`foo/` and `bar/` should have this same timestamp. This timestamping is 
required because this makes it cheap for clients to maintain a copy that is in 
sync with the contents of the storage server without traversing the entire 
storage to locate updated objects.

When implementing _remoteStorage_ on regular file systems it can be quite heavy 
to implement this when a file directory listing request comes in, so it may 
be beneficial to implement this while storing (new) files using `PUT` requests. 
At that time a _touch_ on all directories containing the updated file up to the 
*storageRoot* will take care of implementing this behavior.

## Store a file
A file is stored using the `PUT` verb. There is no `POST` because we want to 
specify the name of the resource the file will be stored under. Storing a 
file is straightforward.

If a directory a file belongs to does not exist already it will be silently 
created.

### Request

    PUT /remoteStorage/api/john.doe/calendar/2012/10/24 HTTP/1.1
    Content-Type: application/json

    [{"start":"10:00","end":"11:00","activity":"Book hostel"},{"start":"11:00","end":"11:30","activity":"Subscribe to conference"}]

### Response

    HTTP/1.1 200 OK

As mentioned in the section above about directory listings it may be better to 
implement the timestamping of directories here depending on the file storage 
backend.

In addition, the `Content-Type` of the file MUST be retained and given back to
the client on file retrieval. This is especially important for `public` files.

In case a `PUT` request specifies a directory, i.e. the URL ends with a 
forward slash (`/`), an error needs to be given back to the client:

    HTTP/1.1 400 Bad Request
    Content-Type: application/json

    {"error":"invalid_request","error_description":"cannot store a directory"}

**FIXME**: this may be a problem when implementing versioning and the 
`Content-Type` header needs to contain the version?

In case large files need to be uploaded it SHOULD be possible to upload files 
in pieces. This is done by supplying the `Content-Range` header in accordance
with the HTTP protocol:

    PUT /remoteStorage/api/john.doe/calendar/2012/10/24 HTTP/1.1
    Content-Type: application/json
    Content-Range: bytes 21010-47021/47022
    Content-Length: 26012
    
    art":"11:00","end":"11:30","ac...

If the server supports the `Content-Range` header in `PUT` requests, it MUST
replace the given bytes in the file or extend it. If the file it is extended,
the skipped parts MUST be filled with zero-bytes.

## Delete a file
Files can be deleted using the `DELETE` verb. Directories cannot be deleted. 

### Request

    DELETE /remoteStorage/api/john.doe/calendar/2012/10/24 HTTP/1.1

### Response

    HTTP/1.1 200 OK

In case a request for deletion specifies a directory, i.e. the URL ends with a 
forward slash, an error needs to be given back to the client:

    HTTP/1.1 400 Bad Request
    Content-Type: application/json

    {"error":"invalid_request","error_description":"a directory cannot be deleted"}

## Concurrency Control

In `GET` and `PUT` requests, the server SHOULD understand the following request
headers and act according to the HTTP protocol, as this is vital to implement
concurrency control in the clients:

* `If-Match`
* `If-None-Match`

## Public Files
There is a "special" `public` directory in the *storageRoot* indicating that all 
files under this directory are public. This means the `GET` call for a file and
ONLY a file, not a directory listing, is allowed without authorization. All 
other requests need authorization as if they were not public. Requesting a file 
list with `GET` while the request is authorized is possible.

## Cross Origin Headers
The `OPTIONS` request should result in a response that tells web browsers that 
cross origin requests are allowed.

### Request 

    OPTIONS /remoteStorage/api/john.doe/calendar/2012/10/24 HTTP/1.1

### Response

    Access-Control-Allow-Origin: *
    Access-Control-Allow-Headers: Content-Type, Authorization, Origin, Content-Range, Content-Length, If-Match, If-None-Match
    Access-Control-Allow-Methods: GET, PUT, DELETE

## Error Handling
Some of the errors that can occur were already shown in the previous sections. 
However, those were all related to the client making a "mistake". In case an
error occurs on the server, i.e.: the disk is full and a file can no longer
be stored a separate error needs to be given back to the user. 

### Server Error

    HTTP/1.1 500 Internal Server Error
    Content-Type: application/json

    {"error":"internal_server_error","error_description":"disk full"}

These errors typically mean that the storage server has a bug or is not 
managed well, e.g.: disk full.

### Authorization Error
It is also possible the an application tries to access or write files or 
directories to which it does not have any permission. For example the user 
`john.doe` authorized an application to access his calendar data, but the 
application now tries to access data belonging to `jane.doe`, i.e. go outside
the *storageRoot* directory. This should not be allowed and an error should be 
returned:

    HTTP/1.1 403 Forbidden
    Content-Type: application/json

    {"error":"access_denied","error_description":"storage path belongs to other user"}

This error is only relevant if there is some way for an application to refer
to another user's *storageRoot*. Depending on the implementation this may not
even be possible.

Other errors relating to e.g. the OAuth scope are specified in _The OAuth 2.0 
Authorization Framework: Bearer Token Usage_. This MUST be followed.

# Authorization
For the authorization, i.e.: what application is allowed to do what _The OAuth 
2.0 Authorization Framework_ specification needs to be implemented. The above
remoteStorage service is referred to as the "Resource Server" while the OAuth 
service distributing access tokens is referred to as the "Authorization Server".

The OAuth service MUST implement the "Implict Grant" token type as specified in
the OAuth specification (RFC XXXX) in section 4.2. Furthermore, the "Bearer" 
token type (RFC XXXX) MUST be implemented.

The OAuth specification recommends that access tokens, i.e.: Bearer tokens, do
expire and recommend a 1 hour expiry time. Due to the nature of user agent 
based applications this may result in user experience degradation as the 
browser needs to fetch a new access token every hour. It is left to the 
implementor to implement access token expiry, however to improve user experience 
it is RECOMMENDED to use an expiry time between 8 and 24 hours.

## Scopes
The scopes from the OAuth specification are also used in the _remoteStorage_ 
API. Every application that wants to use _remoteStorage_ needs to have the 
correct scope for the directory it wants to access. The scope encodes both the
directory and whether _read_ or _write_ access is requested. A `GET` can be 
performed with _read_ permissions, while a `PUT` and `DELETE` require _write_ 
permissions. The scope is encoded like this:

* `<directory>:r` - _read_ permission
* `<directory>:rw` - _read_ and _write_ permission

This permission is sufficient for all directories under this directory. For 
instance:

    PUT /remoteStorage/api/john.doe/calendar/2012/10/24 HTTP/1.1

requires `calendar:rw` permissions. The `calendar` part of the scope refers to
the first directory after the *storageRoot*. This scope is also valid for the 
`public` directory, so the same scope is valid for the next request:

    PUT /remoteStorage/api/john.doe/public/calendar/2012/10/24 HTTP/1.1

The following request only requires `calendar:r` permissions:

    GET /remoteStorage/api/john.doe/calendar/2012/10/ HTTP/1.1

The _remoteStorage_ server needs to verify whether the scope matches the 
directory the application wants to access. So when `calendar:rw` permissions 
are given, it is valid only for `/calendar/*` and `/public/calendar/*`.

There are also two special scope values:

* `:r`
* `:rw`

These scopes, without directory, indicate they are valid for *all* directories 
under *storageRoot*. Care should be taken to not give this permission to 
applications that do not need it.

## OAuth Client Registration

**FIXME**: this should not be here! Out of scope!

All applications need to be registered in the OAuth server as clients. This can 
be done automatically be subscribing the server to a trusted "app store" 
that provides a list of verified applications. 

The format of this manifest this "app store" SHOULD provide is shown below. It 
is using the Chrome Web Store format, It contains a list of all applications 
available from the app store:

    [
        {
            "app": {
                "launch": {
                    "web_url": "https://todomvc.example.org/"
                }, 
                "urls": [
                    "https://todomvc.example.org/"
                ]
            }, 
            "description": "Manage your TODO list.", 
            "icons": {
                "128": "https://todomvc.example.org/icon_x128.png"
                "64": "https://todomvc.example.org/icon_x64.png"
            }, 
            "key": "yiZH3dk49O4n", 
            "name": "TodoMVC", 
            "permissions": [
                "tasks:rw"
            ], 
            "version": "1"
        }, 
        { 
            ...
        },
        {
            ...
        },
        ...
    ]

The important fields for the application registration from the manifest and
their mapping to OAuth terminology are:

<table>
<tr><th>App Manifest</th><th>OAuth</th><tr>
<tr><td>key</td><td>client_id</td></tr>
<tr><td>permissions</td><td>scope</td></tr>
<tr><td>web_url</td><td>redirect_uri</td></tr>
</table>

This manifest data can be (automatically) imported into the OAuth client 
registration database and be used by the portal as a list of available 
applications.

The OAuth service MAY provide an API to the portal to retrieve a list of 
available applications and make it possible to register user consent for a 
specific application to get access to their files as an optimization for the 
user experience.

# Launching Applications

The applications can be launched by providing it with additional parameters in 
the fragment part of the URL. There are two parameters specified:

* `storage_root` - URL pointing to the *storageRoot*
* `authorize_endpoint` - URL pointing to the OAuth authorize endpoint

The `storage_root` parameter is for example like specified before: 
`https://www.example.org/remoteStorage/api/john.doe/` 
while the OAuth authorize endpoint points to the OAuth server in 
`authorize_endpoint`, for example: `https://auth.example.org/oauth2/authorize`.

A full URL then looks like this:

    https://todomvc.example.org/#storage_root=https://www.example.org/remoteStorage/api/john.doe/&authorize_endpoint=https://auth.example.org/oauth2/authorize

# Protocol Versioning

The application and the storage server need to negotiate a version of the 
protocol to use. 

The following header specifies that you want to use the latest version of the 
specification implemented by the server. This is NOT RECOMMENDED for production
use:

    X-RemoteStorage-Version: *

To specify a specific version use the following value:

    X-RemoteStorage-Version: remoteStorage.2012.10

If the request version is not supported by the server an error message SHOULD
to be sent back to the client indicating this:

    HTTP/1.1 406 Not Acceptable
    Content-Type: application/json
    
    {"error":"unsuppored_version","error_description":"the requested version is not supported"}

Additionally, every server response MUST contain the version header (even if
the request did not contain such a header) indicating the remoteStorage protocol
version used:

    X-RemoteStorage-Version: remoteStorage.2012.10

# Storage First
In order to solve the storage discovery problems as mentioned in the 
introduction introduced a scenario for "Storage First" is described. In this 
scenario the user will browse to a URL which provides an "app launch" or 
portal screen. This portal would provide a list of all available and installed 
applications and allows the user to launch them.

For example, this launch screen can provide a list of all registered OAuth 
clients and make it possible to launch them by using the `redirect_uri` 
together with the parameters provided in the previous section. 

For example, the launch screen has a list of applications like this:

    <ul>
      <li><a href="https://todomvc.example.org/#storage_root=https://www.example.org/remoteStorage/api/john.doe/&authorize_endpoint=https://auth.example.org/oauth2/authorize">TodoMVC</a></li>
      <li>...</li>
      <li>...</li>
      <li>...</li>
    </ul>

As an optimization the portal can also register the "consent" from the user 
immediately in the OAuth authorization server, thus reducing the steps the user
needs to take when launching an application. The registration of this "consent"
could be triggered by e.g. an "Install" button for each application. 

Depending on the OAuth authorization server there may be an API available to do
this from the portal site. However, this API is out of scope for this 
specification.

# TODO
* specify something for the management of the OAuth client registrations?
* remove references to portal as much as possible as it is not really relevant 
  to implement _remoteStorage_, put it in one section
* describe the API to register user consent this is a MAY (optimization)
* apps MUST/SHOULD? be registered in OAuth client table

# References
* The OAuth 2.0 Authorization Framework
* The OAuth 2.0 Authorization Framework: Bearer Token Usage
* https://developers.google.com/chrome/apps/docs/developers_guide#manifest

