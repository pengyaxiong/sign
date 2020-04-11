//简单的表格数据解析封装
var k_thead='';
var k_elem = '';
var k_url = '';
var k_page=1;
var index='';
function set_table(thead,url,elem){
		index = layer.msg('加载中', {
		  icon: 16
		  ,shade: 0.01
		});
	
	
	k_thead = thead;
	k_elem = elem;
	k_url = url;
	k_page =1;
	get_data(url,set_table_html);
	return false;
	
}
function set_table_html(thead,data){

	layer.close(index);
	var table = '<table class="layui-table sq-table">';
	thead_str = set_thead(thead);
	tbody_str= set_tbody(data.data,thead);
	page_str = set_page(data.current_page,data.last_page);
	table +=thead_str;
	table +=tbody_str;
	table +='</table>';
	table +=page_str;
	$(k_elem).html(table);
	//console.log($(k_elem));
	//return table;
}

function get_data(url,callBack){
	data = '';
	$.ajax({
		url : url+'&page='+k_page,
		type : 'get',
		success : function(data){
			callBack(k_thead,data)
		},
		fail:function(){
		//code here...
		}
	});
}

function set_thead(thead){
	var thead_str="<thead><tr>";
	for (var key in thead) {
	   // console.log(key);     //获取key值
	  //  console.log(thead[key]); //获取对应的value值
		thead_str+='<th>'+thead[key]+'</th>';
	}
	thead_str+=' </tr></thead>';
	//console.log(thead_str);
	return thead_str;
}

function set_tbody(tbody,thead){
	var tbody_str="<tbody>";
	if(tbody.length==0){
		tbody_str+='<tr class=""><td colspan="6"><img style="width: 500px;max-width: 500px" src="../../../../static/images/abnor_no_data.png?x-oss-process=style/default"><p style="font-size: 16px;margin-top: 20px;margin-bottom: 20px">暂无数据</p></td></tr>';
	}else{
		for(var i=0;i<tbody.length;i++){
			tbody_str+='<tr>';
			for (var key in thead) {
				if(tbody[i][key]=='null' || tbody[i][key]==undefined){
					tbody_str+='<td>0</td>';
				}else{
					tbody_str+='<td>'+tbody[i][key]+'</td>';
				}
				
			}
			tbody_str+='</tr>';
		}
	}
	tbody_str+='</tbody>';
	return tbody_str;
}
function k_last_page(page){
	k_page=page-1;
	get_data(k_url,set_table_html);
}
function k_next_page(page){
	k_page=page+1;
	get_data(k_url,set_table_html);
}
function k_this_page(page){
	k_page=page;
	get_data(k_url,set_table_html);
}
function set_page(current_page,last_page){
	if(last_page>1){
		var page_str = '<div class="sq-page">';
		if(current_page-1>0){
			page_str+='<li><a href="javascript:void(0)" onclick="k_last_page('+current_page+')">«</a></li>';
		}else{
			page_str+='<li class="disabled"><span>«</span></li>';
		}
		for(var i=1;i<10;i++){
			if(last_page<10){
				if(last_page>=i){
					//总页数小于10
					if(current_page==i){
						//当前页
						page_str+='<li class="active"><span>'+i+'</span></li>';
					}else{
						page_str+='<li><a href="javascript:void(0)" onclick="k_this_page('+i+')">'+i+'</a></li>';
						
					}
				}
				
			}else if(last_page>=10){
				if(current_page<6){
					if(current_page==i){
						//当前页
						page_str+='<li class="active"><span>'+i+'</span></li>';
					}else{
						if(i>6){
							if(i<8){
								page_str+='<li class="disabled"><span>...</span></li>';
							}else{
								page_str+='<li><a href="javascript:void(0)" onclick="k_this_page('+(last_page-(9-i))+')">'+(last_page-(9-i))+'</a></li>';
							}
							
						}else{
							page_str+='<li><a href="javascript:void(0)" onclick="k_this_page('+i+')">'+i+'</a></li>';
						}
						
						
					}
				}else if(current_page>=6 && current_page<(last_page-5)){
					if(i<3){
						page_str+='<li><a href="javascript:void(0)" onclick="k_this_page('+i+')">'+i+'</a></li>';
					}else if(i<4){
						page_str+='<li class="disabled"><span>...</span></li>';
					}else if(i<7){
						page = current_page+(i-5);
						if(current_page == page){
							page_str+='<li class="active"><span>'+page+'</span></li>';
						}else{
							page_str+='<li><a href="javascript:void(0)" onclick="k_this_page('+page+')">'+page+'</a></li>';
						}
					}else if(i<8){
						page_str+='<li class="disabled"><span>...</span></li>';
					}else{
						page = last_page-(9-i);
						page_str+='<li><a href="javascript:void(0)" onclick="k_this_page('+page+')">'+page+'</a></li>';
					}
				}else if(current_page>=(last_page-5)){
					if(i<3){
						page_str+='<li><a href="javascript:void(0)" onclick="k_this_page('+i+')">'+i+'</a></li>';
					}else if(i<4){
						page_str+='<li class="disabled"><span>...</span></li>';
					}else{
						page = last_page-(9-i);
						if(current_page==page){
							page_str+='<li class="active"><span>'+page+'</span></li>';
						}else{
							page_str+='<li><a href="javascript:void(0)" onclick="k_this_page('+page+')">'+page+'</a></li>';
						}
						
					}
					
				}
			
			}
			
		}
		if(current_page+1<=last_page){
			page_str+='<li><a href="javascript:void(0)" onclick="k_next_page('+current_page+')">»</a></li>';
		}else{
			page_str+='<li class="disabled">»</li>';
		}
		
		return page_str;
	}
	return ' ';
}