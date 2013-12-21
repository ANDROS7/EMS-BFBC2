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
#include "base64.h"
#include "CFramework.h"

extern CFramework* fw;

/*
        Encode a string to base64 and return it
*/
char* b64_encode(char *input, int length)
{
	BIO *bmem, *b64;
	BUF_MEM *bptr;

	b64 = BIO_new(BIO_f_base64());
	bmem = BIO_new(BIO_s_mem());
	b64 = BIO_push(b64, bmem);
	BIO_write(b64, input, length);
	BIO_flush(b64);
	BIO_get_mem_ptr(b64, &bptr);

	char *buff = (char *)malloc(bptr->length);
	memcpy(buff, bptr->data, bptr->length-1);
	buff[bptr->length-1] = 0;

	BIO_free_all(b64);

	return buff;
}


/*
	 Decode a string to plain text and return it
*/
static const std::string base64_chars = 
             "ABCDEFGHIJKLMNOPQRSTUVWXYZ"
             "abcdefghijklmnopqrstuvwxyz"
             "0123456789+/";


static inline bool is_base64(unsigned char c) {
  return (isalnum(c) || (c == '+') || (c == '/'));
}

char* b64_decode(char *input, int length)
{
	BIO *b64, *bmem;

	char *buffer = (char *)malloc(length);
	memset(buffer, 0, length);

	b64 = BIO_new(BIO_f_base64());
	bmem = BIO_new_mem_buf(input, length);
	bmem = BIO_push(b64, bmem);
	BIO_set_flags(bmem, BIO_FLAGS_BASE64_NO_NL);

	int test = BIO_read(bmem, buffer, length);

	BIO_free_all(bmem);

	return buffer;
}

