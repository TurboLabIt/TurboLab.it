## this should be sourced from .bashrc

# background color per-host
if [[ $- == *i* && -n "$SSH_CONNECTION" ]]; then

    case "$(hostname -s)" in
      turbolab.it)
        # very dark red
        fxSetBackgroundColor "#F2F2F2" "#240000"
        ;;
      next-tli)
        # very dark orange
        fxSetBackgroundColor "#F2F2F2" "#241600"
        ;;
    esac

  # Ensure colors go back to default when the shell exits
  trap fxResetBackgroundColor EXIT

fi


cd /var/www/turbolab.it
