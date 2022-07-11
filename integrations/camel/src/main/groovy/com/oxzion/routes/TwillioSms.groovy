package com.oxzion.routes

import groovy.json.JsonBuilder
import groovy.json.JsonSlurper
import org.apache.camel.Exchange
import org.apache.camel.Processor
import org.apache.camel.builder.RouteBuilder
import org.springframework.boot.context.properties.EnableConfigurationProperties
import org.springframework.stereotype.Component
import org.springframework.core.env.Environment
import org.springframework.beans.factory.annotation.Autowired
import javax.annotation.PostConstruct
import org.slf4j.Logger
import org.slf4j.LoggerFactory

import com.twilio.Twilio
import com.twilio.rest.api.v2010.account.Message
import com.twilio.type.PhoneNumber

/**
 * Client for sending sms over twillio.
 *
 */
@Component
@EnableConfigurationProperties

public class TwillioSms extends RouteBuilder {
	private static final Logger logger = LoggerFactory.getLogger(SendSmtpMail.class);

	@Autowired
	private Environment env

    @PostConstruct
	public void init() {}

	@Override
	public void configure() {
		from("activemq:queue:twillio_sms").doTry().process(new Processor() {
			public void process(Exchange exchange) throws Exception {
				logger.info("Sending twillio sms")
				def jsonSlurper = new JsonSlurper()
				def object = jsonSlurper.parseText(exchange.getMessage().getBody() as String)
				logger.info("Processing Message with payload ${object}")
				Twilio.init(object.config.accountSid  as String, object.config.authToken  as String)
				Message message = Message.creator(
					new PhoneNumber(object.to as String),
					new PhoneNumber(object.config.from  as String),
					object.body as String
				).create();
				logger.info("Message sent!");
			}
		}).log("Received body ").doCatch(Exception.class).process(new Processor() {
			void process(Exchange exchange) throws Exception {
				Exception exception = (Exception) exchange.getProperty(Exchange.EXCEPTION_CAUGHT)
				def params = [to: 'twillio_sms']
				def jsonparams = new JsonBuilder(params).toPrettyString()
				def stackTrace = new JsonBuilder(exception).toPrettyString()
				ErrorLog.log('activemq_queue', stackTrace,exchange.getMessage().getBody().toString(),jsonparams)
				System.out.println("handling ex")
			}
		})
	}
}
