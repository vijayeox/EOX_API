package com.oxzion.routes

import groovy.json.JsonBuilder
import groovy.json.JsonSlurper
import org.apache.camel.CamelContext
import org.apache.camel.Exchange
import org.apache.camel.Processor
import org.apache.camel.builder.RouteBuilder
import org.apache.camel.component.properties.PropertiesComponent
import org.apache.camel.impl.DefaultCamelContext
import org.springframework.boot.context.properties.EnableConfigurationProperties
import org.springframework.stereotype.Component
import org.apache.activemq.command.ActiveMQTextMessage

import javax.activation.DataHandler
import javax.activation.FileDataSource
import org.slf4j.Logger
import org.slf4j.LoggerFactory

/**
 * Client for sending mails over smtp.
 * @author Bharat Gogineni
 *
 */
@Component
@EnableConfigurationProperties

public class SendSmtpMail extends RouteBuilder {
    private static final Logger logger = LoggerFactory.getLogger(SendSmtpMail.class)
    @Override
    void configure() throws Exception {
        CamelContext context = new DefaultCamelContext()
        PropertiesComponent pc = getContext().getComponent('properties', PropertiesComponent.class)
        context.addComponent('properties', pc)
        context.addRoutes(new RouteBuilder() {

            @Override
            public void configure() {
                def smtp_host = getContext().resolvePropertyPlaceholders('{{smtp.host}}')
                def serverPath = "smtp://${smtp_host}?mail.smtp.auth=false"
                if ( smtp_host != 'localhost' ) {
                    def smtp_port = getContext().resolvePropertyPlaceholders('{{smtp.port}}')
                    def smtp_username = getContext().resolvePropertyPlaceholders('{{smtp.username}}')
                    def smtp_password = getContext().resolvePropertyPlaceholders('{{smtp.password}}')
                    serverPath = "smtps://${smtp_host}:${smtp_port}?username=${smtp_username}&password=${smtp_password}"
                }
                from('activemq:queue:mail').doTry().process(new Processor() {

                    public void process(Exchange exchange) throws Exception {
                        def jsonSlurper = new JsonSlurper()
                        def messageIn  = exchange.getIn()
                        def object = jsonSlurper.parseText(exchange.getMessage().getBody() as String)
                        logger.info("Processing Email with payload ${object}")

                        def debug = false
                        def toList = ''
                        try {
                            toList = getContext().resolvePropertyPlaceholders('{{smtp.to.email}}')
                            if (!toList) { throw new Exception('smtp.to.email empty...') }
                            debug = true
                        } catch (Exception ex) {
                            if (object.to) { toList = setMessageHeader(object.to) }
                        }
                        if (toList) {
                            messageIn.setHeader('To', toList)
                        }

                        if (!debug) {
                            if (object.cc) {
                                messageIn.setHeader('Cc', setMessageHeader(object.cc))
                            }

                            def bccList = ''
                            try {
                                bccList = getContext().resolvePropertyPlaceholders('{{smtp.bcc.email}}') + ', '
                            } catch (Exception ex) {
                                bccList = ''
                            }
                            if (object.bcc) {
                                bccList += setMessageHeader(object.bcc)
                            }
                            if (bccList) {
                                messageIn.setHeader('Bcc', bccList)
                            }
                        }
                        if (object.replyTo) {
                            messageIn.setHeader('replyTo', setMessageHeader(object.replyTo))
                        }

                        logger.info('Processing Email with from address' + object.from)
                        logger.info('Processing Email with default from address' + getContext().resolvePropertyPlaceholders('{{smtp.from.email}}'))
                        def mailFrom = (object.from) ?: getContext().resolvePropertyPlaceholders('{{smtp.from.email}}')
                        messageIn.setHeader('From', setMessageHeader(mailFrom))

                        def mailSubject = (object.subject) ?: getContext().resolvePropertyPlaceholders('{{default.subject}}')
                        messageIn.setHeader('Subject', mailSubject as String)

                        def mailBody = (object.body) ?: ''
                        messageIn.setBody(mailBody as String)
                        messageIn.setHeader('Content-Type', 'text/html')

                        logger.info('Mail Headers' + messageIn.getHeaders() as String)
                        if (object.attachments) {
                            logger.info('Has attachments:')
                            if (object.attachments.size() > 0) {
                                def attachmentList = object.attachments as ArrayList
                                for (int i = 0; i < attachmentList.size(); i++) {
                                    def fileLocation = new File(attachmentList.get(i) as String)
                                    def fileName = fileLocation.getAbsolutePath().substring(fileLocation.getAbsolutePath().lastIndexOf('/') + 1)
                                    logger.info('fileName: ' + fileName)
                                    messageIn.addAttachment(fileName, new DataHandler(new FileDataSource(fileLocation)))
                                }
                            }
                        }
                    }

                }).log('Received body ').to(serverPath).doCatch(Exception.class).process(new Processor() {

                    void process(Exchange exchange) throws Exception {
                        Exception exception = (Exception) exchange.getProperty(Exchange.EXCEPTION_CAUGHT)
                        def params = [to: 'mail']
                        def jsonparams = new JsonBuilder(params).toPrettyString()
                        def stackTrace = new JsonBuilder(exception).toPrettyString()
                        logger.info("Processing Email with payload ${exchange.getMessage().getJmsMessage()}")
                        def message = exchange.getMessage().getJmsMessage()
                        if (message instanceof ActiveMQTextMessage) {
                            ActiveMQTextMessage textMessage = (ActiveMQTextMessage) message
                            try {
                                String json = message.getText()
                                logger.info(json)
                                ErrorLog.log('activemq_queue', stackTrace, json, jsonparams)
                            } catch (Exception e) {
                                logger.info("Could not extract data to log from TextMessage - ${e}")
                            }
                        }
                    }

                })
            }

        })
        context.start()
    }

    def setMessageHeader(header) {
        def list = ''
        def recepientList = header instanceof String ? [header] : header as ArrayList
        for (int i = 0; i < recepientList.size(); i++) {
            def recepient = recepientList.get(i)
            list += (i < recepientList.size() - 1) ? recepient + ', ' : recepient
        }
        return list
    }

}
