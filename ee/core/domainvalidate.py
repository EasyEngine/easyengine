from urllib.parse import urlparse


def validate_domain(url):

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
        return final_domain
    else:
        final_domain = domain_name
        return final_domain
