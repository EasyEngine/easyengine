#compdef ee

function _ee {

	_ee_completion() {
		ee_completion=()
		ee_completion=$(sudo /usr/local/bin/ee cli completions --shell='zsh' --line="ee $current_command " --point="$current_position")

		completion=()
		while read line; do
			completion+=("${line}")
		done < <(echo "$ee_completion")
	}

	_ee_service_list() {
		ee_services=$(sudo docker-compose -f /opt/easyengine/services/docker-compose.yml ps --services | sed 's/global\-//g')
		completion=()
		while read line; do
			completion+=("${line}")
		done < <(echo "$ee_services")
	}

	_ee_site_list() {
		ee_sites=$(sqlite3 /opt/easyengine/db/ee.sqlite "select site_url from sites;")
		completion=()
		while read line; do
			completion+=("${line}")
		done < <(echo "$ee_sites")
	}

	if [[ "${words[2]}" == "cli" || "${words[2]}" == "config" || "${words[2]}" == "help" ]]; then
		_arguments '1: :-> sub_commands' '2: :->sub_command_param' '*: :->flags'

	elif [[ "${words[2]}" == "service" ]]; then
		_arguments '1: :-> sub_commands' '2: :->sub_command_param' '3: :->get_service_name' '*: :->flags'

	elif [[ "${words[2]}" == "shell" ]]; then
		_arguments '1: :-> sub_commands' '2: :->get_site_name' '*: :->flags'

	elif [[ `pwd` == /opt/easyengine/sites*/* ]]; then
		_arguments '1: :-> sub_commands' '2: :->sub_command_param' '*: :->flags'

	elif [[ "${words[2]}" == "site" && "${words[3]}" == "create" ]]; then
		_arguments '1: :-> sub_commands' '2: :->sub_command_param' '*: :->flags'

	else
		_arguments '1: :-> sub_commands' '2: :->sub_command_param' '3: :->get_site_name' '*: :->flags'
	fi

	current_command=""
	current_position=0

	case $state in
		sub_commands)

			current_command=${words[@]:1}
			current_position=$((CURRENT + 1))

			_ee_completion
			_describe 'command' completion
			;;

		sub_command_param)

			chrlen="ee ${words[@]:1}"
			current_command=${words[@]:1}
			current_position=$((${#chrlen}))

			_ee_completion

			_describe 'command' completion
			;;

		get_site_name)

			_ee_site_list
			_describe 'command' completion
			;;

		get_service_name)

			_ee_service_list
			_describe 'command' completion
			;;

		flags)

			chrlen="ee ${words[@]:1}"
			current_command=${words[@]:1}
			current_position=$((${#chrlen}))

			_ee_completion
			_describe 'command' completion

			;;

		*)
			echo "Error occured in auto completion"
			;;
	esac
}
