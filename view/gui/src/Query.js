import React from 'react';
import { query as section } from '../metadata.json';
import { Form, Row, Col, Button, Tabs, Tab } from 'react-bootstrap'
import OX_Grid from "./OX_Grid"
import  Notification from "./Notification"
import Switch from 'react-switch'
import QueryModal from './components/Modals/QueryModal'
import QueryResult from './components/Query/QueryResult'
class Query extends React.Component {
  constructor(props) {
    super(props);
    this.core = this.props.args;
    this.props.setTitle(section.title.en_EN);
    this.notif = React.createRef();
    this.state = {
      dataSourceOptions: [],
      inputs: {},
      errors: {},
      showQueryModal: false,
      modalType: "",
      modalContent: {},
      checked: {}
    }
    this.refresh = React.createRef();
    this.handleSwitch = this.handleSwitch.bind(this);
    this.checkedList = {}

  }

  componentWillMount() {
    //set switch respect to activated and deactivated datasource
    this.setState({ checked: this.checkedList })
  }

  handleSwitch(checked, event, id) {
    let toggleList = { ...this.state.checked }
    toggleList[id] = checked
    this.setState({ checked: toggleList });
  }
  async fetchDataSource() {
    let helper = this.core.make('oxzion/restClient');
    let response = await helper.request('v1', 'analytics/datasource?show_deleted=true', {}, 'get');//stat from here
    
    this.setState({ dataSourceOptions: response.data })
  }

  handleChange(e, instance) {
    let name = ""
    let value = ""
    let errors = this.state.errors
    errors[e.target.name] = ""
    if (e.target.name === "datasourcename") {
      const selectedIndex = e.target.options.selectedIndex;
      let uuid = e.target.options[selectedIndex].getAttribute('data-key')
      name = e.target.name
      value = [e.target.value, uuid];
      errors["datasourcename"] = ""
    }
    else {
      name = e.target.name
      value = e.target.value;
      errors["query"] = ""
    }
    instance.setState({ inputs: { ...instance.state.inputs, [name]: value, errors: errors } })
  }
  componentDidMount() {
    this.fetchDataSource()
  }

  validateform() {
    let validForm = true;
    let errors = {}
    if (!this.state.inputs["datasourcename"]) {
      validForm = false
      errors["datasourcename"] = "*Please select the datasource";
    }
    if (!this.state.inputs["configuration"]) {
      errors["query"] = "*Please enter the query";
      validForm = false
    }
    this.setState({ errors: errors })
    return validForm
  }
  onsaveQuery() {
    this.validateform() ? this.setState({ showQueryModal: true ,modalContent:"",modalType:"Save"}) : null
  }

  renderEmpty() {
    return [<React.Fragment key={1} />];
  }
  replaceParams(route, params) {
    if (!params) {
      return route;
    }
    var regex = /\{\{.*?\}\}/g;
    let m;
    while ((m = regex.exec(route)) !== null) {
      // This is necessary to avoid infinite loops with zero-width matches
      if (m.index === regex.lastIndex) {
        regex.lastIndex++;
      }

      // The result can be accessed through the `m`-variable.
      m.forEach((match, groupIndex) => {
        route = route.replace(match, params[match.replace(/\{\{|\}\}/g, "")]);
      });
    }
    return route;
  }

  queryOperation = (e, operation) => {
    this.setState({ showQueryModal: true, modalContent: e, modalType: operation })
  }
  async buttonAction(action, item) {
    if (action.name !== undefined) {
      if (action.name === "toggleActivate" && item.isdeleted == "0")
      this.queryOperation(item, "Delete")
    else if (action.name === "toggleActivate" && item.isdeleted == "1")
      this.queryOperation(item, "Activate")
    }
  }
  renderButtons(e, action) {

    var actionButtons = [];
    Object.keys(action).map(function (key, index) {
      var string = this.replaceParams(action[key].rule, e);
      var showButton = eval(string);
      if (action[key].name === "toggleActivate") {
        this.checkedList[e.name] = showButton //check if the datasource is deleted or not
        showButton = true   //always show the button
      }
      var buttonStyles = action[key].icon
        ? {
          width: "auto"
        }
        : {
          width: "auto",
          // paddingTop: "5px",
          color: "white",
          fontWeight: "600"
        };
      showButton
        ? action[key].name === "toggleActivate" ?
        actionButtons.push(
          <abbr title={this.checkedList[e.name] ? "Deactivate" : "Activate"} key={index}>
            <Switch
              id={e.name}
              onChange={() => this.buttonAction(action[key], e)}
              checked={this.state.checked[e.name]}
              onClick={() => this.buttonAction(action[key], e)}
              onColor="#86d3ff"
              onHandleColor="#2693e6"
              handleDiameter={10}
              uncheckedIcon={false}
              checkedIcon={false}
              boxShadow="0px 1px 5px rgba(0, 0, 0, 0.6)"
              activeBoxShadow="0px 0px 1px 10px rgba(0, 0, 0, 0.2)"
              height={20}
              width={33}
              className="react-switch"
            />
          </abbr>
        )
      :null
        : null;
    }, this);
    return actionButtons;
  }

  render() {
    return (
      <div className="query full-height">
        <Notification ref={this.notif} />
        <div className="query-form">
          <Form>
            <Form.Group as={Row}>
              <Form.Label column lg="3">Data Source Name</Form.Label>
              <Col lg="9">
                <Form.Control
                  as="select"
                  onChange={(e) => this.handleChange(e, this)}
                  value={this.state.inputs["datasourcename"] !== undefined ? this.state.inputs["datasourcename"][0] : -1}
                  name="datasourcename">
                  <option disabled value={-1} key={-1}></option>
                  {this.state.dataSourceOptions.map((option, index) => (
                    <option key={option.uuid} data-key={option.uuid} value={option.name}>{option.name}</option>
                  ))}

                </Form.Control>
                <Form.Text className="text-muted errorMsg">
                  {this.state.errors["datasourcename"]}
                </Form.Text>
              </Col>
            </Form.Group>
            <Form.Group as={Row}>
              <Form.Label column lg="3">Configuration:</Form.Label>
              <Col lg="9">
                <Form.Control
                  placeholder="Enter your Query here"
                  as="textarea"
                  row="2"
                  name="configuration"
                  value={this.state.inputs["query"]}
                  onChange={(e) => this.handleChange(e, this)}
                />
                <Form.Text className="text-muted errorMsg">
                  {this.state.errors["query"]}
                </Form.Text>
              </Col>
            </Form.Group>
            <Button className="" onClick={() => this.validateform()} ><i class="fa fa-gear"></i> Run Query</Button>
            <Button onClick={() => this.onsaveQuery()}>Save Query</Button>
          </Form>
        </div>
        <div className="query-result-div">

          <Tabs defaultActiveKey="results" id="uncontrolled-tab-example">
            <Tab eventKey="results" title="Result">
              <QueryResult />
            </Tab>
            <Tab eventKey="allqueries" title="All Queries">
              <div className="col=md-12 querylist-div">

                <OX_Grid
                  ref={this.refresh}
                  osjsCore={this.core}
                  data={"analytics/query?show_deleted=true"}
                  filterable={true}
                  reorderable={true}
                  sortable={true}
                  pageable={true}
                  columnConfig={[
                    {
                      title: "Name", field: "name"
                    },
                    {
                      title: "Actions",
                      cell: e =>
                        this.renderButtons(e, [
                          {
                            name: "toggleActivate", rule: "{{isdeleted}}==0", icon: "fa fa-trash"
                          }
                        ]),
                      filterCell: e => this.renderEmpty()
                    }
                  ]}
                />
              </div>

            </Tab>
          </Tabs>
        </div>

        {/* <div className="query-result-div">
          <OX_Grid
            osjsCore={this.core}
            data={"analytics/datasource"}
            filterable={true}
            reorderable={true}
            sortable={true}
            pageable={true}
            columnConfig={[
              {
                title: "Name", field: "name"
              }

            ]}
          />
        </div> */}
        <QueryModal
          osjsCore={this.core}
          modalType={this.state.modalType}
          show={this.state.showQueryModal}
          refreshGrid={this.refresh}
          content={this.state.modalContent}
          onHide={() => this.setState({ showQueryModal: false })}
          configuration={this.state.inputs["configuration"]}
          datasourcename={this.state.inputs["datasourcename"] != undefined ? this.state.inputs["datasourcename"][0] : ""}
          datasourceuuid={this.state.inputs["datasourcename"] != undefined ? this.state.inputs["datasourcename"][1] : ""}
          notification={this.notif}
          resetInput={()=>this.setState({inputs:{}})}
        />

      </div>
    );
  }
}

export default Query;

