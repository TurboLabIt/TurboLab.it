# [Gestione blacklist Cloudmark](https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/email-blacklist-cloudmark.md)

Capita di sovente che *Cloudmark* metta il nostro server in blacklist. È utilizzato da vari provider italiani (*Libero*, *Virgilio*, ...),
i cui utenti non ricevono più le notifiche del forum, la newsletter e tutte le altre email che inviamo direttamente dal server.

[MXToolbox non rileva](https://mxtoolbox.com/SuperTool.aspx?action=blacklist%3aturbolab.it&run=toolpage) il blocco.

Generalmente viene segnalato tramite una email simile a questa:

> <ma**ke@libero.it>: host smtp-in.libero.it[213.209.1.129] refused to talk
> to me: 550 smtp-10.iol.local smtp-10.iol.local IP blacklisted by CSI. For
> remediation please use
> http://csi.cloudmark.com/reset-request/?ip=95.141.32.225
> [smtp-10.iol.local; LIB_102

Per richiedere lo sblocco, visitare l'URL indicato e compilare il form con i seguenti valori:

- *Domain name that the IP in the reset request represents*: `turbolab.it`
- *Exact SMTP 5xx error string received*: copiare il messaggio indicato sopra
- *Comment*: `We run a double-optin only free service. We NEVER send an email to someone who didn't request it. Please unblock us ASAP. Thanks`

⚠ Al submit del form viene inviata una email al richiedente con oggetto `Confirm CSI IP Address Statistics Reset Request` e un link di conferma.
