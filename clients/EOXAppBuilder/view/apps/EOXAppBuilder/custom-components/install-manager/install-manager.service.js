export class InstallManagerService {
  updateOrganization = null;
  selectedOrganization = null;
  orgTemplateSelected = null;
  metaData = null;
  constructor(parentData, type, core, appId) {
    this.parentData = parentData;
    this.installationType = type;
    this.core = core;
    this.appId = appId;
    return this;
  }

  setOrganization(org) {
    this.selectedOrganization = org;
    return this;
  }

  updateOrganizationData(org) {
    this.updateOrganization = org;
    return this;
  }

  setMetadata(metadata) {
    this.metaData = metadata;
    return this;
  }

  goBack() {
    if (this.updateOrganization) {
      this.updateOrganization = null;
      return this;
    }
    if (this.metaData) {
      this.metaData = null;
      return this;
    }
    if (this.selectedOrganization) {
      this.selectedOrganization = null;
      return this;
    }
    return this;
  }
}
