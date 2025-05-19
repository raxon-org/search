{{$register = Package.Raxon.Search:Init:register()}}
{{if(!is.empty($register))}}
{{Package.Raxon.Search:Import:role.system()}}
{{Package.Raxon.Search:Main:search.install(flags(), options())}}
{{/if}}