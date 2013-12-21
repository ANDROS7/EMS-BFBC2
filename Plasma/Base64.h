/********************************************************************************************
        Base64 encoding implementation based on openSSL

        Author: CARSON
        Date: 11.04.2006
        URL: http://www.ioncannon.net/programming/34/howto-base64-encode-with-cc-and-openssl/

        -- EDIT --
        Author: Martin Albrecht <martin.albrecht@javacoffee.de>
        Date: 22.08.2009
        URL: http://code.google.com/p/smtpmail

DEPENDENCIES:
-------------
        - openSSL-devel (http://www.deanlee.cn/programming/openssl-for-windows/)

DESCRIPTION:
------------
        This is a simple base64 encoding implementation,
        based on the openSSL library.
        I found this code on http://www.ioncannon.net

        To compile this code you need the openSSL development
        libraries. In Windows I copied all the headers and lib
        files into my Dev-Cpp folder and compiled the code with
        the command:

                gcc base64.c -l libeay32

        In Linux/Unix it is:

                gcc base64.c -l ssl

        WINDOWS NOTE: After compiling, you need the libeay32.dll file in
        the directory of your compiled binary!

********************************************************************************************/

#include "stdafx.h"

#pragma once

char *b64_encode(char *input, int length);
char *b64_decode(char *string, int length);


