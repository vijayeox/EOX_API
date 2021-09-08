package com.oxzion.routes

import groovy.json.JsonOutput
import groovy.json.JsonSlurper
import org.apache.camel.CamelContext
import org.apache.camel.Exchange
import org.apache.camel.Processor
import org.apache.camel.builder.RouteBuilder
import org.apache.camel.component.properties.PropertiesComponent
import org.apache.camel.impl.DefaultCamelContext
import org.apache.http.auth.AuthScope;
import org.apache.http.client.CredentialsProvider;
import org.apache.http.impl.client.BasicCredentialsProvider
import org.apache.http.impl.nio.client.HttpAsyncClientBuilder;
import org.apache.http.auth.UsernamePasswordCredentials;
import org.apache.http.HttpHost
import org.elasticsearch.ElasticsearchException
import org.elasticsearch.client.RestClientBuilder;
import org.elasticsearch.action.bulk.BulkItemResponse
import org.elasticsearch.action.bulk.BulkRequest
import org.elasticsearch.action.bulk.BulkResponse
import org.elasticsearch.action.delete.DeleteRequest
import org.elasticsearch.action.index.*
import org.elasticsearch.client.RequestOptions
import org.elasticsearch.client.RestClient
import org.elasticsearch.client.RestHighLevelClient
import org.elasticsearch.common.xcontent.XContentType
import org.slf4j.Logger
import org.slf4j.LoggerFactory
import org.springframework.context.annotation.PropertySource
import org.springframework.stereotype.Component
import org.springframework.core.env.Environment
import org.springframework.beans.factory.annotation.Autowired

@Component
class ElasticClientIndexer extends RouteBuilder {

    @Autowired
    private Environment env

    private static final Logger logger = LoggerFactory.getLogger(ElasticClientIndexer.class)

    @Override
    void configure() throws Exception {
        CamelContext context = new DefaultCamelContext()
        context.addRoutes(new RouteBuilder() {
            @Override
            public void configure() {
                from("activemq:queue:elastic").process(new Processor() {
                    public void process(Exchange exchange) throws Exception {
                        def jsonSlurper = new JsonSlurper()
                        def object = jsonSlurper.parseText(exchange.getMessage().getBody())
                        def HOST = env.getProperty("elastic.host")
                        def CORE = env.getProperty("elastic.core")
                        def USERNAME = env.getProperty("elastic.user")
                        def PASSWORD = env.getProperty("elastic.password")
                        int PORT = env.getProperty("elastic.port").toInteger()
                        def idList,deleteList
                        String ID;
                        String indexName = object.index.toString().toLowerCase()
                        if (CORE != '' && CORE != null) {
                            indexName = CORE.toString() + '_' + indexName;
                        }
                        String type = object.type.toString()
                        if(object.containsKey('id')){
                            ID = object.id.toString()
                        }
                        if(object.containsKey('idlist')){
                            idList = object.idlist
                        }
                        if(object.containsKey('deleteList')){
                            deleteList = object.deleteList
                        }
                        String operation = object.operation.toString()
                        final CredentialsProvider credentialsProvider =
                        new BasicCredentialsProvider();
                        credentialsProvider.setCredentials(AuthScope.ANY, new UsernamePasswordCredentials(USERNAME, PASSWORD));
                        def output = JsonOutput.toJson(object.body)
                        RestClientBuilder builder = RestClient.builder(new HttpHost(HOST, PORT, "https")).setHttpClientConfigCallback(new RestClientBuilder.HttpClientConfigCallback() {
                            @Override
                            public HttpAsyncClientBuilder customizeHttpClient(
                                HttpAsyncClientBuilder httpClientBuilder) {
                                return httpClientBuilder.setDefaultCredentialsProvider(credentialsProvider);
                            }
                            });
                        def client = new RestHighLevelClient(builder)
                        try{
                            if(operation == 'Index')
                            {
                                def request = new IndexRequest(indexName,type,ID)
                                request.source(output, XContentType.JSON)
                                client.index(request, RequestOptions.DEFAULT)
                            }
                            else if(operation == 'Delete')
                            {
                                def deleteRequest = new DeleteRequest(indexName,type,ID)
                                client.delete(deleteRequest, RequestOptions.DEFAULT)
                            }
                            else if(operation == 'Bulk')
                            {
                                BulkRequest bulk = new BulkRequest()
                                for(obj in object.body) {
                                    String id = obj.id
                                    String content = JsonOutput.toJson(obj)
                                    bulk.add(new IndexRequest(indexName,type,id).source(content, XContentType.JSON))
                                }
                                BulkResponse bulkResponse = client.bulk(bulk, RequestOptions.DEFAULT)
                                for (BulkItemResponse bulkItemResponse : bulkResponse) {
                                    if (bulkItemResponse.isFailed()) {
                                        BulkItemResponse.Failure failure =
                                                bulkItemResponse.getFailure()
                                        logger.error("BULK INDEXING (EXCEPTION) Failure is---"+failure)
                                    }
                                }
                            }
                            else if(operation == 'Batch')
                            {
                                int i = 0
                                BulkRequest bulk = new BulkRequest()
                                for(obj in object.body) {
                                    String id = idList[i].toString()
                                    ++i
                                    String content = JsonOutput.toJson(obj)
                                    bulk.add(new IndexRequest(indexName,type,id).source(content, XContentType.JSON))
                                }
                                if(deleteList){
                                    for(del in deleteList) {
                                        String id = del.toString()
                                        bulk.add(new DeleteRequest(indexName,type,id))
                                    }
                                }
                                BulkResponse bulkResponse = client.bulk(bulk, RequestOptions.DEFAULT)
                                for (BulkItemResponse bulkItemResponse : bulkResponse) {
                                    if (bulkItemResponse.isFailed()) {
                                        BulkItemResponse.Failure failure =
                                                bulkItemResponse.getFailure()
                                        logger.error("BULK INDEXING (EXCEPTION) Failure is---"+failure)
                                    }
                                }
                            }
                            else
                            {
                                throw new Exception("Incorrect operation specified :" + operation)
                            }
                        }
                        catch(ElasticsearchException ex)
                        {
                            println("the exception is -"+ex)
                            logger.error("INDEXING (EXCEPTION) ---",ex)
                        }
                    }
                }).to("log:notification")

            }
        })
        context.start()
    }
}
