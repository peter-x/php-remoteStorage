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

Here, `john.doe` is the user identifier, and part of the *storageRoot*. 

A user identifier does not need to be a part of the *storageRoot*, it is up
to the implementor to decide about this. The only requirements are that the 
*storageRoot* is bound to the authenticated user and that it there is some
way to have a `public` directory under this root belonging to the user.

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

    [{"start":"09:00","end":"10:00","activity":"Shopping"},{"start":"12:00","end":"13:00","activity":"Lunch"}]

The `Content-Type` given back to the client MUST be identical to the value that
was specified while uploading the file using `PUT`, described in the section 
below.

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

**FIXME**: this may be a problem when implementing versioning and the 
`Content-Type` header needs to contain the version?

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
    Access-Control-Allow-Headers: Content-Type, Authorization, Origin
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

Other errors relating to the OAuth scope are specified by the HTTP Bearer 
specification. The recommendations there MUST be followed.

# Authorization
For the authorization, i.e.: what application is allowed to do what a subset of
the OAuth specification is taken. The HTTP Bearer specification however is 
used in its entirety. The OAuth server needs to implement version 2 of the 
protocol. The following is a list of what is needed for the implementation:

* Support the "Implicit Grant" type from section 4.2;
* Bearer access tokens do not expire, i.e. they are valid until revoked;

Before an application can use the _remoteStorage_ API it needs to obtain a 
`Bearer` token. This token is opaque, i.e.: a secure random generated string 
encoded to the base64 alphabet as specified in the HTTP Bearer token 
specification.

Furthermore, the access token needs to have a scope belonging to the directory
the application wants to access. 

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

# Application Registration
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

# Application Launch

The applications are launched from the portal by providing it with additional
parameters in the fragment part of the URL. There are two parameters 
specified:

* `rs_api_uri` - URL pointing to the *storageRoot* at the storage server
* `rs_authz_uri` - URL pointing to the OAuth authorize endpoint

The `rs_api_uri` parameter is for example like specified before: 
`https://www.example.org/remoteStorage/api/john.doe/` 
while the OAuth authorize endpoint points to the OAuth server in `rs_authz_uri`, 
for example: `https://auth.example.org/oauth2/authorize`.

A full URL then looks like this:

    https://todomvc.example.org/#rs_api_uri=https://www.example.org/remoteStorage/api/john.doe/&rs_authz_uri=https://auth.example.org/oauth2/authorize

# Versioning
The application and the storage server need to negotiate a version of the 
protocol to use. 

The following header specifies that you want to use the latest version of the 
specification implemented by the server. This is NOT RECOMMENDED for production
use:

    Accept: application/json

To specify a specific version use the following `Accept` header:

    Accept: application/vnd.remoteStorage.2012.10+json

If the request version is not supported by the server an error message SHOULD
to be sent back to the client indicating this:

    HTTP/1.1 406 Not Acceptable
    Content-Type: application/json
    
    {"error":"unsuppored_version","error_description":"the requested version is not supported"}

**FIXME**: maybe the server should just return a response anyway of whichever 
version and indicate that in the `Content-Type` header???

# Storage First
In order to avoid the discovery problems introduced by implementing Webfinger
with CORS headers all around the Internet on all user domains a scenario 
for "storage first" is provided. Here, the user will browse to the service 
providing an "app launch" screen. This app launch screen provides a list of all 
available and installed applications and allows the user to launch them.

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

