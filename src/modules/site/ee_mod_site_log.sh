# Checks log

function ee_mod_site_log()
{
		# 
        if [ $# -eq 0 ]; then
                for site in $(ls /etc/nginx/sites-available/*); do
                        ee_log_path="$ee_log_path /var/log/nginx/$(basename $site).*.log"
                done
        else
                for $ee_domain_name in $@; do
                        
                        EE_DOMAIN_CHECK=$ee_domain_name
                        ee_lib_check_domain

                        # Check the website exist
                        ls /etc/nginx/sites-available/$EE_DOMAIN &> /dev/null \
                        || ee_lib_error "The $EE_DOMAIN is not found in /etc/nginx/sites-available, exit status = " $?
                        
                        if [ $? -eq 0 ]; then
                                ee_log_path="$ee_log_path /var/log/nginx/$EE_DOMAIN.*.log"
                        fi
                done
        fi
        tail -f ${ee_log_path}
}
