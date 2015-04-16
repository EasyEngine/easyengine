"""EasyEngine domain validation module."""
from urllib.parse import urlparse


def ValidateDomain(url):
    """
        This function returns domain name removing http:// and https://
        returns domain name only with or without www as user provided.
    """

    # Check if http:// or https://  present remove it if present
    domain_name = url.split('/')
    if 'http:' in domain_name or 'https:' in domain_name:
        domain_name = domain_name[2]
    else:
        domain_name = domain_name[0]
    www_domain_name = domain_name.split('.')
    final_domain = ''
    if www_domain_name[0] == 'www':
        final_domain = '.'.join(www_domain_name[1:])
    else:
        final_domain = domain_name

    return (final_domain, domain_name)
