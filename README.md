RESTful API server in Symfony 3
=========

This is an example of how a RESTful API server may be done with Symfony 3.

At this moment it is compliant with [JSON API](http://jsonapi.org/) only.

It does not separate business and service layer correctly - there is a lot going on in the controller instead of dedicated services. It makes it harder to write test cases.

## TODO ##

* Separate business and service layers
* Change and complete test cases
* Support for other content types depending on the Accept type header
