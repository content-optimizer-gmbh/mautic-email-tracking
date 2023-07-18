# Mautic Email Tracking Control Plugin

Plugin disables link tracking depending on email id, urls in emails or a lead field value 

## Plugin Configuration: 
<img width="617" alt="Bildschirmfoto 2023-07-18 um 11 45 31" src="https://github.com/content-optimizer-gmbh/mautic-email-tracking/assets/50595160/d0e3acb4-8447-488a-8a3a-d83866841c50">



## Explaination of fields: 

Lead field alias to use for do not track flag: Alias of lead field to use for the decision to track links or not track links (see example of custom field below) 

Do not track email with ids (csv): E-Mail Ids to not track

Do not track urls (csv): List of links to not track in emails 


Custom Field Example: 
<img width="1012" alt="Bildschirmfoto 2023-07-18 um 11 49 13" src="https://github.com/content-optimizer-gmbh/mautic-email-tracking/assets/50595160/6832107d-f7d7-4d81-88fc-2f020f0e4d8c">


## How to Install

The current version was tested with Mautic 4.4.5

1. Download the ZIP file
2. Extract it to a local directory
3. Upload the contents to your Mautic into /plugins/JotaworksEmailTrackingBundle
4. Clear the mautic cache 
5. Navigate to the Plugins page
6. Click "Refresh / Install plugin"

Done! The plugin should now appear in the list of plugins.

